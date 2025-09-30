"""Interactive interface for running the universe simulator."""

from __future__ import annotations

import queue
import subprocess
import threading
from pathlib import Path
from typing import Iterable, List

import tkinter as tk
from tkinter import messagebox, ttk


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

        self._output_queue: "queue.Queue[str]" = queue.Queue()
        self._worker: threading.Thread | None = None

        self._build_layout()

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

        button_frame = ttk.Frame(self)
        button_frame.pack(side=tk.TOP, fill=tk.X, padx=12, pady=(0, 12))
        ttk.Button(button_frame, text="Run", command=self.run_command).pack(side=tk.LEFT)
        ttk.Button(button_frame, text="Clear Output", command=self.clear_output).pack(side=tk.LEFT, padx=(8, 0))

        output_frame = ttk.Frame(self)
        output_frame.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=12, pady=(0, 12))

        self.output_text = tk.Text(output_frame, wrap=tk.WORD, state=tk.DISABLED)
        self.output_text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)

        scrollbar = ttk.Scrollbar(output_frame, orient=tk.VERTICAL, command=self.output_text.yview)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.output_text.configure(yscrollcommand=scrollbar.set)

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


def main() -> None:
    gui = UniverseGUI()
    gui.mainloop()


if __name__ == "__main__":
    main()
