#!/usr/bin/env python3
"""Generate portrait assets for simulator entities.

The tool prefers Hugging Face `diffusers` pipelines when available and
falls back to a deterministic procedural PNG generator when the
dependencies are missing. Generated images can be written to disk or
emitted as a JSON document containing base64-encoded PNG data alongside
metadata that the PHP metadata store can ingest.
"""

from __future__ import annotations

import argparse
import base64
import io
import json
import math
import os
import random
import struct
import sys
import time
import zlib
from typing import Any, Dict, Tuple


def _build_argument_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Generate visual assets for the universe simulator.")
    parser.add_argument("prompt", help="Text prompt describing the desired artwork.")
    parser.add_argument("--output", "-o", help="Optional path to write the PNG image.")
    parser.add_argument("--format", choices={"png", "json"}, default="png", help="Output format when writing to stdout.")
    parser.add_argument("--width", type=int, default=512, help="Image width in pixels (default: 512).")
    parser.add_argument("--height", type=int, default=512, help="Image height in pixels (default: 512).")
    parser.add_argument("--seed", type=int, default=None, help="Optional RNG seed for repeatable results.")
    parser.add_argument(
        "--model",
        default="runwayml/stable-diffusion-v1-5",
        help="Diffusers model identifier to use when available.",
    )
    parser.add_argument("--steps", type=int, default=30, help="Diffusion steps when using diffusers (default: 30).")
    parser.add_argument("--guidance", type=float, default=7.5, help="Classifier-free guidance scale (default: 7.5).")
    parser.add_argument(
        "--no-diffusers",
        action="store_true",
        help="Force the fallback procedural renderer even if diffusers is installed.",
    )
    return parser


def _generate_placeholder(width: int, height: int, seed: int | None, prompt: str) -> Tuple[bytes, Dict[str, Any]]:
    rng = random.Random(seed)
    rows = []
    for y in range(height):
        row = bytearray([0])  # PNG filter byte
        for x in range(width):
            phase = rng.random()
            red = int((x / max(1, width - 1)) * 255)
            green = int((y / max(1, height - 1)) * 255)
            blue = int((0.5 + 0.5 * math.sin(phase * math.pi * 2)) * 255)
            row.extend((red, green, blue))
        rows.append(bytes(row))
    pixel_data = b"".join(rows)
    png_bytes = _encode_png(width, height, pixel_data)
    metadata = {
        "generator": "artisan:placeholder",
        "prompt": prompt,
        "width": width,
        "height": height,
        "created_at": time.time(),
        "attributes": {"mode": "gradient"},
    }
    return png_bytes, metadata


def _encode_png(width: int, height: int, data: bytes) -> bytes:
    def chunk(chunk_type: bytes, chunk_data: bytes) -> bytes:
        return (
            struct.pack(">I", len(chunk_data))
            + chunk_type
            + chunk_data
            + struct.pack(">I", zlib.crc32(chunk_type + chunk_data) & 0xFFFFFFFF)
        )

    header = b"\x89PNG\r\n\x1a\n"
    ihdr = struct.pack(">IIBBBBB", width, height, 8, 2, 0, 0, 0)  # 8-bit RGB
    idat = zlib.compress(data, level=9)
    return header + chunk(b"IHDR", ihdr) + chunk(b"IDAT", idat) + chunk(b"IEND", b"")


def _generate_with_diffusers(
    prompt: str,
    width: int,
    height: int,
    steps: int,
    guidance: float,
    model: str,
    seed: int | None,
) -> Tuple[bytes, Dict[str, Any]]:
    from diffusers import StableDiffusionPipeline  # type: ignore
    import torch  # type: ignore

    torch_dtype = torch.float16 if torch.cuda.is_available() else torch.float32
    pipe = StableDiffusionPipeline.from_pretrained(model, torch_dtype=torch_dtype)
    if torch.cuda.is_available():
        pipe = pipe.to("cuda")
    generator = torch.Generator(device=pipe.device)
    if seed is not None:
        generator = generator.manual_seed(seed)
    result = pipe(
        prompt,
        width=width,
        height=height,
        num_inference_steps=max(1, steps),
        guidance_scale=guidance,
        generator=generator,
    )
    image = result.images[0]
    buffer = io.BytesIO()
    image.save(buffer, format="PNG")
    png_bytes = buffer.getvalue()
    metadata = {
        "generator": f"diffusers:{model}",
        "prompt": prompt,
        "width": image.width,
        "height": image.height,
        "created_at": time.time(),
        "attributes": {"steps": steps, "guidance": guidance},
    }
    if seed is not None:
        metadata["seed"] = seed
    return png_bytes, metadata


def main(argv: list[str] | None = None) -> int:
    parser = _build_argument_parser()
    args = parser.parse_args(argv)

    seed = args.seed
    if seed is None:
        seed = random.randint(0, 2**31 - 1)

    use_diffusers = not args.no_diffusers
    generator_info = None

    if use_diffusers:
        try:
            png_bytes, metadata = _generate_with_diffusers(
                args.prompt,
                max(64, args.width),
                max(64, args.height),
                args.steps,
                args.guidance,
                args.model,
                seed,
            )
            generator_info = metadata
        except Exception as exc:  # pragma: no cover - optional dependency
            sys.stderr.write(
                f"[artisan] Unable to use diffusers pipeline ({exc}). Falling back to procedural renderer.\n"
            )

    if generator_info is None:
        png_bytes, metadata = _generate_placeholder(max(16, args.width), max(16, args.height), seed, args.prompt)

    if args.output:
        output_path = os.fspath(args.output)
        with open(output_path, "wb") as handle:
            handle.write(png_bytes)

    if args.format == "json":
        payload = {
            "mime_type": "image/png",
            "data": base64.b64encode(png_bytes).decode("ascii"),
            "metadata": metadata,
        }
        sys.stdout.write(json.dumps(payload))
    elif not args.output:
        # Emit raw PNG bytes to stdout if no output path and non-JSON format.
        sys.stdout.buffer.write(png_bytes)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
