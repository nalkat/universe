"""Interactive interface for running the universe simulator."""

from __future__ import annotations

import json
import math
import queue
import subprocess
import threading
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

import importlib.util
import sys

if importlib.util.find_spec("tkinter") is None:
    sys.stderr.write(
        "Universe GUI requires the tkinter module. Install Tk support (e.g. python3-tk) "
        "and retry.\n"
    )
    sys.exit(1)

try:
    import tkinter as tk
    from tkinter import messagebox, ttk
except ModuleNotFoundError as exc:  # pragma: no cover - environment dependent
    missing = exc.name or "tkinter"
    sys.stderr.write(
        "Universe GUI could not import '{missing}'. Ensure Tk support is installed for "
        "the Python interpreter at {python}.\n".format(
            missing=missing,
            python=sys.executable,
        )
    )
    if missing == "_tkinter":
        sys.stderr.write(
            "On Debian/Ubuntu, install the matching python3-tk package (e.g. sudo apt "
            "install python3-tk) and verify Tk libraries are present.\n"
        )
    sys.exit(1)


class UniverseGUI(tk.Tk):
    """A lightweight Tkinter shell to run the PHP universe simulator."""

    def __init__(self) -> None:
        super().__init__()
        self.title("Universe Simulator Control Panel")
        self.geometry("900x600")
        self.minsize(800, 520)

        self.project_root = Path(__file__).resolve().parents[1]
        self.php_binary = tk.StringVar(value="php")
        self.mode = tk.StringVar(value="run_once")
        self.steps = tk.StringVar(value="10")
        self.delta = tk.StringVar(value="3600")
        self.loop_interval = tk.StringVar(value="1.0")
        self.auto_steps = tk.StringVar(value="1")
        self.socket_path = tk.StringVar(value=str(self.project_root / "runtime" / "universe.sock"))
        self.pid_file_path = tk.StringVar(value=str(self.project_root / "runtime" / "universe.pid"))
        self.foreground = tk.BooleanVar(value=True)
        self.seed = tk.StringVar()
        self.galaxies = tk.StringVar()
        self.systems_per_galaxy = tk.StringVar()
        self.planets_per_system = tk.StringVar()
        self.tick_delay = tk.StringVar(value="0.0")
        self.catalog_refresh_seconds = tk.DoubleVar(value=10.0)
        self.auto_refresh = tk.BooleanVar(value=False)

        self.catalog_data: Dict[str, Any] = {}
        self._catalog_index: Dict[str, Dict[str, Any]] = {}
        self._catalog_refresh_job: int | None = None

        self._output_queue: "queue.Queue[str]" = queue.Queue()
        self._worker: threading.Thread | None = None

        self._build_layout()
        # Ensure refresh label reflects the default DoubleVar value on startup.
        self._update_refresh_label(str(self.catalog_refresh_seconds.get()))

    def _build_layout(self) -> None:
        """Construct widgets."""

        control_frame = ttk.Frame(self)
        control_frame.pack(side=tk.TOP, fill=tk.X, padx=12, pady=12)

        php_label = ttk.Label(control_frame, text="PHP binary:")
        php_label.grid(row=0, column=0, sticky=tk.W, padx=(0, 6))
        php_entry = ttk.Entry(control_frame, textvariable=self.php_binary, width=16)
        php_entry.grid(row=0, column=1, sticky=tk.W)

        mode_frame = ttk.LabelFrame(control_frame, text="Mode")
        mode_frame.grid(row=0, column=2, rowspan=3, padx=(12, 0), sticky=tk.NSEW)
        ttk.Radiobutton(mode_frame, text="Run Once", value="run_once", variable=self.mode).pack(anchor=tk.W, padx=8, pady=4)
        ttk.Radiobutton(mode_frame, text="Start Daemon", value="start", variable=self.mode).pack(anchor=tk.W, padx=8, pady=4)

        run_once_frame = ttk.LabelFrame(control_frame, text="Run Once Options")
        run_once_frame.grid(row=1, column=0, columnspan=2, pady=(12, 0), sticky=tk.EW)
        run_once_frame.columnconfigure(1, weight=1)

        ttk.Label(run_once_frame, text="Steps:").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.steps, width=8).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(run_once_frame, text="Delta (s):").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.delta, width=10).grid(row=0, column=3, sticky=tk.W, pady=4)
        ttk.Label(run_once_frame, text="Tick Delay (s):").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.tick_delay, width=10).grid(row=1, column=1, sticky=tk.W, pady=4)

        daemon_frame = ttk.LabelFrame(control_frame, text="Daemon Options")
        daemon_frame.grid(row=2, column=0, columnspan=2, pady=(12, 0), sticky=tk.EW)
        daemon_frame.columnconfigure(1, weight=1)
        daemon_frame.columnconfigure(3, weight=1)

        ttk.Label(daemon_frame, text="Loop Interval (s):").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.loop_interval, width=10).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(daemon_frame, text="Auto Steps:").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.auto_steps, width=8).grid(row=0, column=3, sticky=tk.W, pady=4)
        ttk.Label(daemon_frame, text="Socket Path:").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.socket_path, width=48).grid(row=1, column=1, columnspan=3, sticky=tk.EW, pady=4)
        ttk.Label(daemon_frame, text="PID File:").grid(row=2, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.pid_file_path, width=48).grid(row=2, column=1, columnspan=3, sticky=tk.EW, pady=4)
        ttk.Checkbutton(daemon_frame, text="Stay in foreground", variable=self.foreground).grid(row=3, column=0, columnspan=4, sticky=tk.W, padx=8, pady=(4, 8))

        generation_frame = ttk.LabelFrame(control_frame, text="Generation Options")
        generation_frame.grid(row=3, column=0, columnspan=3, pady=(12, 0), sticky=tk.EW)
        for column in range(4):
            generation_frame.columnconfigure(column, weight=1)

        ttk.Label(generation_frame, text="Seed:").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.seed, width=12).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Galaxies:").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.galaxies, width=8).grid(row=0, column=3, sticky=tk.W, pady=4)

        ttk.Label(generation_frame, text="Systems/Galaxy:").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.systems_per_galaxy, width=12).grid(row=1, column=1, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Planets/System:").grid(row=1, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.planets_per_system, width=12).grid(row=1, column=3, sticky=tk.W, pady=4)

        button_frame = ttk.Frame(self)
        button_frame.pack(side=tk.TOP, fill=tk.X, padx=12, pady=(0, 12))
        ttk.Button(button_frame, text="Run", command=self.run_command).pack(side=tk.LEFT)
        ttk.Button(button_frame, text="Clear Output", command=self.clear_output).pack(side=tk.LEFT, padx=(8, 0))
        ttk.Button(button_frame, text="Load Catalog", command=self.load_catalog).pack(side=tk.LEFT, padx=(24, 0))

        self.notebook = ttk.Notebook(self)
        self.notebook.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=12, pady=(0, 12))

        console_tab = ttk.Frame(self.notebook)
        self.notebook.add(console_tab, text="Console Output")
        console_frame = ttk.Frame(console_tab)
        console_frame.pack(side=tk.TOP, fill=tk.BOTH, expand=True)

        self.output_text = tk.Text(console_frame, wrap=tk.WORD, state=tk.DISABLED)
        self.output_text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        console_scrollbar = ttk.Scrollbar(console_frame, orient=tk.VERTICAL, command=self.output_text.yview)
        console_scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.output_text.configure(yscrollcommand=console_scrollbar.set)

        catalog_tab = ttk.Frame(self.notebook)
        self.notebook.add(catalog_tab, text="Universe Browser")
        self._build_catalog_tab(catalog_tab)

    def clear_output(self) -> None:
        self.output_text.configure(state=tk.NORMAL)
        self.output_text.delete("1.0", tk.END)
        self.output_text.configure(state=tk.DISABLED)

    def run_command(self) -> None:
        if self._worker is not None and self._worker.is_alive():
            messagebox.showinfo("Universe Simulator", "A command is already running.")
            return

        command = self._build_command()
        if command is None:
            return

        self._append_output("$ " + " ".join(command) + "\n")
        self._worker = threading.Thread(target=self._execute_command, args=(command,), daemon=True)
        self._worker.start()
        self.after(100, self._drain_queue)

    def _build_command(self) -> List[str] | None:
        binary = self.php_binary.get().strip() or "php"
        script_path = self.project_root / "universe.php"
        if not script_path.exists():
            messagebox.showerror("Universe Simulator", f"Unable to locate universe.php at {script_path}.")
            return None

        args: List[str] = [binary, str(script_path)]
        mode = self.mode.get()
        if mode == "run_once":
            args.append("run-once")
            if steps := self.steps.get().strip():
                args.append(f"--steps={steps}")
            if delta := self.delta.get().strip():
                args.append(f"--delta={delta}")
            if tick_delay := self.tick_delay.get().strip():
                args.append(f"--tick-delay={tick_delay}")
        else:
            args.append("start")
            if loop := self.loop_interval.get().strip():
                args.append(f"--interval={loop}")
            if delta := self.delta.get().strip():
                args.append(f"--delta={delta}")
            if auto_steps := self.auto_steps.get().strip():
                args.append(f"--auto-steps={auto_steps}")
            if socket := self.socket_path.get().strip():
                args.append(f"--socket={socket}")
            if pid_file := self.pid_file_path.get().strip():
                args.append(f"--pid-file={pid_file}")
            if self.foreground.get():
                args.append("--no-daemonize")

        if seed := self.seed.get().strip():
            args.append(f"--seed={seed}")
        if galaxies := self.galaxies.get().strip():
            args.append(f"--galaxies={galaxies}")
        if systems := self.systems_per_galaxy.get().strip():
            args.append(f"--systems-per-galaxy={systems}")
        if planets := self.planets_per_system.get().strip():
            args.append(f"--planets-per-system={planets}")
        return args

    def _execute_command(self, command: Iterable[str]) -> None:
        try:
            process = subprocess.Popen(
                list(command),
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                cwd=self.project_root,
            )
        except OSError as exc:
            self._output_queue.put(f"Failed to start command: {exc}\n")
            return

        assert process.stdout is not None
        for line in process.stdout:
            self._output_queue.put(line)
        return_code = process.wait()
        self._output_queue.put(f"\n[process exited with code {return_code}]\n")

    def _append_output(self, text: str) -> None:
        self.output_text.configure(state=tk.NORMAL)
        self.output_text.insert(tk.END, text)
        self.output_text.see(tk.END)
        self.output_text.configure(state=tk.DISABLED)

    def _drain_queue(self) -> None:
        while True:
            try:
                text = self._output_queue.get_nowait()
            except queue.Empty:
                break
            self._append_output(text)
        if self._worker is not None and self._worker.is_alive():
            self.after(100, self._drain_queue)

    def load_catalog(self, silent: bool = False) -> None:
        command = self._build_catalog_command()
        if command is None:
            return
        if not silent:
            self._append_output("$ " + " ".join(command) + "\n")
        try:
            result = subprocess.run(
                command,
                check=True,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.project_root,
            )
        except subprocess.CalledProcessError as exc:
            message = exc.stderr.strip() or str(exc)
            if silent:
                self._append_output(f"Catalog command failed: {message}\n")
            else:
                messagebox.showerror("Universe Simulator", f"Catalog command failed: {message}")
            return

        output = (result.stdout or "").strip()
        if result.stderr and not silent:
            self._append_output(result.stderr)

        data = self._parse_catalog_output(output)
        if data is None:
            if silent:
                self._append_output("Unable to parse catalog output.\n")
            else:
                messagebox.showerror("Universe Simulator", "Unable to parse catalog output.")
            return
        self.catalog_data = data
        self._populate_catalog_tree(data)
        if not silent:
            self._append_output("Catalog loaded.\n")

    def _build_catalog_command(self) -> List[str] | None:
        binary = self.php_binary.get().strip() or "php"
        script_path = self.project_root / "universe.php"
        if not script_path.exists():
            messagebox.showerror("Universe Simulator", f"Unable to locate universe.php at {script_path}.")
            return None

        args: List[str] = [binary, str(script_path), "catalog", "--format=json", "--people-limit=50", "--chronicle-limit=12"]
        if seed := self.seed.get().strip():
            args.append(f"--seed={seed}")
        if galaxies := self.galaxies.get().strip():
            args.append(f"--galaxies={galaxies}")
        if systems := self.systems_per_galaxy.get().strip():
            args.append(f"--systems-per-galaxy={systems}")
        if planets := self.planets_per_system.get().strip():
            args.append(f"--planets-per-system={planets}")
        return args

    def _parse_catalog_output(self, output: str) -> Dict[str, Any] | None:
        if not output:
            return None
        try:
            return json.loads(output)
        except json.JSONDecodeError:
            snippet = self._extract_json_object(output)
            if snippet is not None:
                try:
                    return json.loads(snippet)
                except json.JSONDecodeError:
                    return None
        return None

    def _extract_json_object(self, output: str) -> Optional[str]:
        start = output.find('{')
        while start != -1:
            depth = 0
            in_string = False
            escape = False
            for index in range(start, len(output)):
                char = output[index]
                if in_string:
                    if escape:
                        escape = False
                    elif char == '\\':
                        escape = True
                    elif char == '"':
                        in_string = False
                    continue
                if char == '"':
                    in_string = True
                    continue
                if char == '{':
                    depth += 1
                elif char == '}':
                    depth -= 1
                    if depth == 0:
                        return output[start:index + 1]
            start = output.find('{', start + 1)
        return None

    def _build_catalog_tab(self, parent: ttk.Frame) -> None:
        control = ttk.Frame(parent)
        control.pack(side=tk.TOP, fill=tk.X, padx=12, pady=12)

        ttk.Checkbutton(control, text="Auto-refresh", variable=self.auto_refresh, command=self._auto_refresh_toggle).pack(side=tk.LEFT)
        ttk.Label(control, text="Interval (s):").pack(side=tk.LEFT, padx=(12, 6))
        self.refresh_scale = ttk.Scale(
            control,
            from_=2.0,
            to=120.0,
            orient=tk.HORIZONTAL,
            variable=self.catalog_refresh_seconds,
            command=self._update_refresh_label,
        )
        self.refresh_scale.pack(side=tk.LEFT, fill=tk.X, expand=True)
        self.refresh_value_label = ttk.Label(control, text=f"{self.catalog_refresh_seconds.get():.1f}s")
        self.refresh_value_label.pack(side=tk.LEFT, padx=(6, 0))

        paned = ttk.PanedWindow(parent, orient=tk.HORIZONTAL)
        paned.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=12, pady=(0, 12))

        tree_container = ttk.Frame(paned)
        detail_container = ttk.Frame(paned)
        paned.add(tree_container, weight=1)
        paned.add(detail_container, weight=2)

        columns = ("summary",)
        self.catalog_tree = ttk.Treeview(tree_container, columns=columns, show="tree headings")
        self.catalog_tree.heading("#0", text="Object")
        self.catalog_tree.heading("summary", text="Summary")
        self.catalog_tree.column("#0", width=240)
        self.catalog_tree.column("summary", width=320)
        self.catalog_tree.bind("<<TreeviewSelect>>", self._on_catalog_select)
        tree_scroll = ttk.Scrollbar(tree_container, orient=tk.VERTICAL, command=self.catalog_tree.yview)
        self.catalog_tree.configure(yscrollcommand=tree_scroll.set)
        self.catalog_tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        tree_scroll.pack(side=tk.RIGHT, fill=tk.Y)

        self.visual_canvas = tk.Canvas(detail_container, height=180, background="#0b0f1a", highlightthickness=0)
        self.visual_canvas.pack(side=tk.TOP, fill=tk.X, pady=(0, 8))

        detail_frame = ttk.Frame(detail_container)
        detail_frame.pack(side=tk.TOP, fill=tk.BOTH, expand=True)
        self.catalog_details = tk.Text(detail_frame, wrap=tk.WORD, state=tk.DISABLED)
        self.catalog_details.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        detail_scroll = ttk.Scrollbar(detail_frame, orient=tk.VERTICAL, command=self.catalog_details.yview)
        detail_scroll.pack(side=tk.RIGHT, fill=tk.Y)
        self.catalog_details.configure(yscrollcommand=detail_scroll.set)

    def _update_refresh_label(self, value: str) -> None:
        try:
            seconds = float(value)
        except ValueError:
            seconds = self.catalog_refresh_seconds.get()
        else:
            self.catalog_refresh_seconds.set(seconds)
        self.refresh_value_label.configure(text=f"{seconds:.1f}s")

    def _auto_refresh_toggle(self) -> None:
        if self.auto_refresh.get():
            self._schedule_catalog_refresh()
        else:
            self._cancel_catalog_refresh()

    def _schedule_catalog_refresh(self) -> None:
        self._cancel_catalog_refresh()
        delay = max(1.0, float(self.catalog_refresh_seconds.get()))
        self._catalog_refresh_job = self.after(int(delay * 1000), self._auto_refresh_fetch)

    def _cancel_catalog_refresh(self) -> None:
        if self._catalog_refresh_job is not None:
            self.after_cancel(self._catalog_refresh_job)
            self._catalog_refresh_job = None

    def _auto_refresh_fetch(self) -> None:
        self.load_catalog(silent=True)
        if self.auto_refresh.get():
            self._schedule_catalog_refresh()

    def _populate_catalog_tree(self, data: Dict[str, Any]) -> None:
        if not hasattr(self, "catalog_tree"):
            return
        for item in self.catalog_tree.get_children():
            self.catalog_tree.delete(item)
        self._catalog_index.clear()
        root_id = self._insert_catalog_node("", data)
        self.catalog_tree.item(root_id, open=True)
        self.catalog_tree.selection_set(root_id)
        self._display_catalog_details(data)

    def _insert_catalog_node(self, parent: str, node: Dict[str, Any]) -> str:
        name = str(node.get("name", "Unnamed"))
        summary = str(node.get("summary", ""))
        item_id = self.catalog_tree.insert(parent, tk.END, text=name, values=(summary,))
        self._catalog_index[item_id] = node
        for child in node.get("children", []):
            if isinstance(child, dict):
                self._insert_catalog_node(item_id, child)
        return item_id

    def _on_catalog_select(self, _: object) -> None:
        selection = self.catalog_tree.selection()
        if not selection:
            return
        node = self._catalog_index.get(selection[0])
        if node is None:
            return
        self._display_catalog_details(node)

    def _display_catalog_details(self, node: Dict[str, Any]) -> None:
        lines = []
        category = str(node.get('category', 'object')).title()
        lines.append(f"{category}: {node.get('name', 'Unnamed')}")
        if node.get('summary'):
            lines.append(str(node['summary']))
        if node.get('description'):
            lines.append("")
            lines.append(str(node['description']))

        stats = node.get('statistics')
        if isinstance(stats, dict) and stats:
            lines.append("")
            lines.append("Statistics:")
            for key, value in stats.items():
                lines.append(f"  - {key.replace('_', ' ').title()}: {self._format_value(value)}")

        chronicle = node.get('chronicle')
        if isinstance(chronicle, list) and chronicle:
            lines.append("")
            lines.append("Chronicle:")
            for entry in chronicle[-10:]:
                if isinstance(entry, dict):
                    label = entry.get('type', 'event')
                    text = entry.get('text', '')
                    timestamp = entry.get('timestamp')
                    stamp = ''
                    if isinstance(timestamp, (int, float)):
                        try:
                            stamp = datetime.fromtimestamp(timestamp).isoformat(timespec='seconds')
                        except (OverflowError, OSError, ValueError):
                            stamp = ''
                    suffix = f" ({stamp})" if stamp else ''
                    lines.append(f"  - [{label}] {text}{suffix}")
                else:
                    lines.append(f"  - {entry}")

        metadata = node.get('metadata')
        if isinstance(metadata, dict) and metadata:
            lines.append("")
            lines.append("Metadata:")
            for key, value in metadata.items():
                lines.append(f"  - {key.replace('_', ' ').title()}: {self._format_value(value)}")

        self.catalog_details.configure(state=tk.NORMAL)
        self.catalog_details.delete("1.0", tk.END)
        self.catalog_details.insert(tk.END, "\n".join(lines))
        self.catalog_details.configure(state=tk.DISABLED)
        self._render_visual(node)

    def _format_value(self, value: Any) -> str:
        if isinstance(value, (dict, list)):
            text = json.dumps(value, indent=2)
            if len(text) > 800:
                return text[:797] + "..."
            return text
        return str(value)

    def _render_visual(self, node: Dict[str, Any]) -> None:
        if not hasattr(self, "visual_canvas"):
            return
        canvas = self.visual_canvas
        canvas.delete("all")
        width = canvas.winfo_width()
        height = canvas.winfo_height()
        if width <= 1 or height <= 1:
            canvas.update_idletasks()
            width = canvas.winfo_width()
            height = canvas.winfo_height()
        if width <= 1:
            try:
                width = int(canvas['width'])
            except (KeyError, ValueError, TypeError):
                width = 400
        if height <= 1:
            try:
                height = int(canvas['height'])
            except (KeyError, ValueError, TypeError):
                height = 200
        canvas.create_rectangle(0, 0, width, height, fill="#0b0f1a", outline="")

        category = str(node.get('icon') or node.get('category', 'object')).lower()
        color_map = {
            'universe': '#2f80ed',
            'galaxy': '#bb86fc',
            'system': '#03dac6',
            'star': '#fdd663',
            'planet': '#8ab4f8',
            'country': '#a5d6a7',
            'person': '#ffab91',
            'materials': '#c5e1a5',
            'element': '#81d4fa',
            'compound': '#ffcc80',
        }
        color = color_map.get(category, '#e0e0e0')
        cx, cy = width // 2, height // 2
        radius = min(width, height) // 3

        if category == 'galaxy':
            for arm in range(3):
                points = []
                for step in range(40):
                    angle = (step / 40.0) * 4 * math.pi + (arm * (2 * math.pi / 3))
                    r = (step / 40.0) * radius
                    points.append(cx + r * math.cos(angle))
                    points.append(cy + r * math.sin(angle))
                canvas.create_line(points, fill=color, width=2, smooth=True)
        elif category == 'system':
            planet_count = sum(1 for child in node.get('children', []) if isinstance(child, dict) and child.get('category') == 'planet')
            for idx in range(max(planet_count, 1)):
                orbit = (idx + 1) * radius / max(planet_count, 1)
                canvas.create_oval(cx - orbit, cy - orbit, cx + orbit, cy + orbit, outline="#3f51b5", width=1)
            canvas.create_oval(cx - 12, cy - 12, cx + 12, cy + 12, fill=color_map.get('star', '#fdd663'), outline="")
        elif category == 'planet':
            classification = str(node.get('statistics', {}).get('classification', '')).lower()
            if 'ice' in classification:
                color = '#80deea'
            elif 'gas' in classification:
                color = '#ffd54f'
            elif 'ocean' in classification:
                color = '#4fc3f7'
            elif 'volcanic' in classification:
                color = '#ff8a65'
            canvas.create_oval(cx - radius, cy - radius, cx + radius, cy + radius, fill=color, outline="")
        elif category == 'star':
            canvas.create_oval(cx - radius, cy - radius, cx + radius, cy + radius, fill=color, outline="")
        elif category == 'country':
            size = radius
            points = [
                cx, cy - size,
                cx + size, cy,
                cx, cy + size,
                cx - size, cy,
            ]
            canvas.create_polygon(points, fill=color, outline="#2e7d32", width=2)
        elif category == 'person':
            canvas.create_oval(cx - radius // 2, cy - radius, cx + radius // 2, cy - radius // 2, fill=color, outline="")
            canvas.create_rectangle(cx - radius // 3, cy - radius // 2, cx + radius // 3, cy + radius // 2, fill=color, outline="")
        elif category in {'materials', 'element_group', 'elements'}:
            canvas.create_polygon(
                cx, cy - radius,
                cx + radius, cy - radius // 3,
                cx + radius * 0.7, cy + radius,
                cx - radius * 0.7, cy + radius,
                cx - radius, cy - radius // 3,
                fill=color,
                outline="#558b2f",
                width=2,
            )
        elif category in {'compound', 'compound_group'}:
            for angle in range(0, 360, 72):
                rad = math.radians(angle)
                x = cx + radius * math.cos(rad)
                y = cy + radius * math.sin(rad)
                canvas.create_oval(x - 10, y - 10, x + 10, y + 10, fill=color, outline="")
                canvas.create_line(cx, cy, x, y, fill="#ff7043", width=2)
            canvas.create_oval(cx - 12, cy - 12, cx + 12, cy + 12, fill="#ff7043", outline="")
        else:
            canvas.create_oval(cx - radius, cy - radius, cx + radius, cy + radius, fill=color, outline="")



def main() -> None:
    gui = UniverseGUI()
    gui.mainloop()


if __name__ == "__main__":
    main()
