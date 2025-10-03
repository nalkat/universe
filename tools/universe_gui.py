"""Interactive interface for running the universe simulator."""

from __future__ import annotations

import base64
import json
import math
import os
import queue
import signal
import subprocess
import threading
import time
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

import importlib.util
import sys


def _print_cli_usage() -> None:
    script_name = Path(__file__).name
    sys.stdout.write(
        "Universe GUI usage:\n"
        f"  python3 {script_name} [--help]\n\n"
        "Launch the Tkinter-based desktop control panel for the PHP universe simulator.\n"
    )


def _argv_requests_help(argv: Iterable[str]) -> bool:
    for argument in argv:
        if argument in {"-h", "--help"}:
            return True
    return False


if _argv_requests_help(sys.argv[1:]):
    _print_cli_usage()
    sys.exit(0)

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
        self.steps = tk.StringVar(value="1")
        self.delta = tk.StringVar(value="60")
        self.loop_interval = tk.StringVar(value="2.0")
        self.auto_steps = tk.StringVar(value="1")
        self.socket_path = tk.StringVar(value=str(self.project_root / "runtime" / "universe.sock"))
        self.pid_file_path = tk.StringVar(value=str(self.project_root / "runtime" / "universe.pid"))
        self.foreground = tk.BooleanVar(value=True)
        self.seed = tk.StringVar()
        self.galaxies = tk.StringVar()
        self.systems_per_galaxy = tk.StringVar()
        self.planets_per_system = tk.StringVar()
        self.workers = tk.StringVar(value=str(max(1, os.cpu_count() or 1)))
        self.tick_delay = tk.StringVar(value="2.0")
        self.catalog_refresh_seconds = tk.DoubleVar(value=10.0)
        self.auto_refresh = tk.BooleanVar(value=False)
        self.pause_button_text = tk.StringVar(value="Pause")
        self.status_text = tk.StringVar(value="Idle")
        self.catalog_search = tk.StringVar()

        self.catalog_data: Dict[str, Any] = {}
        self._catalog_index: Dict[str, Dict[str, Any]] = {}
        self._catalog_refresh_job: int | None = None
        self._status_reset_job: int | None = None

        self._output_queue: queue.Queue[Any] = queue.Queue()
        self._catalog_queue: queue.Queue[Any] = queue.Queue()
        self._worker: threading.Thread | None = None
        self._catalog_worker: threading.Thread | None = None
        self._process: subprocess.Popen[str] | None = None
        self._paused: bool = False

        self.run_button: ttk.Button | None = None
        self.pause_button: ttk.Button | None = None
        self.stop_button: ttk.Button | None = None
        self._control_panel: "ControlPanel" | None = None
        self._console_window: "ConsoleWindow" | None = None
        self._output_history: List[str] = []
        self._visual_base_image: tk.PhotoImage | None = None
        self._visual_display_image: tk.PhotoImage | None = None

        self._build_layout()
        # Ensure refresh label reflects the default DoubleVar value on startup.
        self._update_refresh_label(str(self.catalog_refresh_seconds.get()))
        self._update_status("Idle")

    def _build_layout(self) -> None:
        """Construct widgets."""

        self._configure_style()
        self._build_menu()

        content = ttk.Frame(self, style="App.TFrame")
        content.pack(side=tk.TOP, fill=tk.BOTH, expand=True)

        self._build_toolbar(content)

        browser_container = ttk.Frame(content, style="App.TFrame")
        browser_container.pack(side=tk.TOP, fill=tk.BOTH, expand=True)
        self._build_catalog_tab(browser_container)

        self._build_status_bar()

    def _configure_style(self) -> None:
        style = ttk.Style()
        try:
            style.theme_use("clam")
        except tk.TclError:
            pass

        background = "#0f172a"
        secondary = "#1e293b"
        highlight = "#1d4ed8"

        style.configure("App.TFrame", background=background)
        style.configure("Toolbar.TFrame", background=background)
        style.configure("Status.TFrame", background=background)
        style.configure("App.TLabel", background=background, foreground="#e2e8f0")
        style.configure("Highlight.TLabel", background=background, foreground="#cbd5f5")
        style.configure("Toolbar.TButton", padding=6)
        style.map("Toolbar.TButton", background=[("active", highlight)])
        style.configure(
            "Catalog.Treeview",
            background=background,
            foreground="#e2e8f0",
            fieldbackground=background,
            rowheight=22,
            borderwidth=0,
        )
        style.map(
            "Catalog.Treeview",
            background=[("selected", highlight)],
            foreground=[("selected", "#f8fafc")],
        )
        style.configure("Detail.TNotebook", background=background, borderwidth=0)
        style.configure(
            "Detail.TNotebook.Tab",
            padding=(12, 6),
            background=secondary,
            foreground="#cbd5f5",
        )
        style.map(
            "Detail.TNotebook.Tab",
            background=[("selected", highlight)],
            foreground=[("selected", "#f8fafc")],
        )

    def _build_toolbar(self, parent: ttk.Frame) -> None:
        toolbar = ttk.Frame(parent, style="Toolbar.TFrame")
        toolbar.pack(side=tk.TOP, fill=tk.X, padx=12, pady=(12, 8))

        left_group = ttk.Frame(toolbar, style="Toolbar.TFrame")
        left_group.pack(side=tk.LEFT, fill=tk.X, expand=True)

        ttk.Button(
            left_group,
            text="Load Catalog",
            style="Toolbar.TButton",
            command=self.load_catalog,
        ).pack(side=tk.LEFT)

        ttk.Checkbutton(
            left_group,
            text="Auto Refresh",
            variable=self.auto_refresh,
            command=self._auto_refresh_toggle,
        ).pack(side=tk.LEFT, padx=(12, 0))

        ttk.Label(left_group, text="Interval (s):", style="Highlight.TLabel").pack(side=tk.LEFT, padx=(12, 6))
        self.refresh_spin = ttk.Spinbox(
            left_group,
            from_=1.0,
            to=3600.0,
            increment=1.0,
            textvariable=self.catalog_refresh_seconds,
            width=7,
        )
        self.refresh_spin.pack(side=tk.LEFT)
        self.refresh_spin.bind("<Return>", lambda _: self._update_refresh_label(self.refresh_spin.get()))
        self.refresh_spin.bind("<FocusOut>", lambda _: self._update_refresh_label(self.refresh_spin.get()))
        self.refresh_value_label = ttk.Label(
            left_group,
            text=f"{self.catalog_refresh_seconds.get():.1f}s",
            style="Highlight.TLabel",
        )
        self.refresh_value_label.pack(side=tk.LEFT, padx=(6, 0))

        right_group = ttk.Frame(toolbar, style="Toolbar.TFrame")
        right_group.pack(side=tk.RIGHT)

        ttk.Label(right_group, text="Search:", style="Highlight.TLabel").pack(side=tk.LEFT, padx=(0, 6))
        search_entry = ttk.Entry(right_group, textvariable=self.catalog_search, width=28)
        search_entry.pack(side=tk.LEFT)
        search_entry.bind("<Return>", self._perform_catalog_search)
        ttk.Button(right_group, text="Find", command=self._perform_catalog_search, style="Toolbar.TButton").pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(right_group, text="Clear", command=self._clear_catalog_search, style="Toolbar.TButton").pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(right_group, text="Console", command=self.show_console, style="Toolbar.TButton").pack(side=tk.LEFT, padx=(12, 0))
        ttk.Button(right_group, text="Controls", command=self.open_control_panel, style="Toolbar.TButton").pack(side=tk.LEFT, padx=(6, 0))

    def _build_status_bar(self) -> None:
        status_frame = ttk.Frame(self, style="Status.TFrame")
        status_frame.pack(side=tk.BOTTOM, fill=tk.X, padx=12, pady=(0, 8))
        ttk.Separator(status_frame, orient=tk.HORIZONTAL).pack(side=tk.TOP, fill=tk.X, pady=(0, 6))
        indicator = ttk.Frame(status_frame, style="Status.TFrame")
        indicator.pack(side=tk.TOP, fill=tk.X)
        ttk.Label(indicator, text="Status:", style="Highlight.TLabel").pack(side=tk.LEFT)
        self.status_label = ttk.Label(indicator, textvariable=self.status_text, style="App.TLabel")
        self.status_label.pack(side=tk.LEFT, padx=(6, 0))
        ttk.Button(indicator, text="Open Console", command=self.show_console).pack(side=tk.RIGHT)

    def _build_menu(self) -> None:
        menubar = tk.Menu(self)

        simulation_menu = tk.Menu(menubar, tearoff=False)
        simulation_menu.add_command(label="Run Simulation", command=self.run_command)
        simulation_menu.add_command(label="Stop Simulation", command=self.stop_command)
        simulation_menu.add_command(label="Reset Configuration", command=self.reset_interface)
        simulation_menu.add_separator()
        simulation_menu.add_command(label="Clear Console Log", command=self.clear_output)
        simulation_menu.add_separator()
        simulation_menu.add_command(label="Exit", command=self.destroy)
        menubar.add_cascade(label="Simulation", menu=simulation_menu)

        catalog_menu = tk.Menu(menubar, tearoff=False)
        catalog_menu.add_command(label="Load Catalog", command=self.load_catalog)
        catalog_menu.add_checkbutton(
            label="Auto Refresh",
            variable=self.auto_refresh,
            onvalue=True,
            offvalue=False,
            command=self._auto_refresh_toggle,
        )
        catalog_menu.add_command(label="Clear Search", command=self._clear_catalog_search)
        menubar.add_cascade(label="Catalog", menu=catalog_menu)

        window_menu = tk.Menu(menubar, tearoff=False)
        window_menu.add_command(label="Simulation Console", command=self.show_console)
        window_menu.add_command(label="Simulation Controls", command=self.open_control_panel)
        menubar.add_cascade(label="Window", menu=window_menu)

        help_menu = tk.Menu(menubar, tearoff=False)
        help_menu.add_command(label="About the Browser", command=self._show_help_dialog)
        menubar.add_cascade(label="Help", menu=help_menu)

        self.config(menu=menubar)

    def open_control_panel(self) -> None:
        panel = self._control_panel
        if panel is None or not panel.winfo_exists():
            self._control_panel = ControlPanel(self)
        else:
            panel.deiconify()
            panel.lift()
            panel.focus_force()

    def close_control_panel(self, panel: "ControlPanel") -> None:
        if self._control_panel is panel:
            self._control_panel = None
            self.run_button = None
            self.pause_button = None
            self.stop_button = None

    def clear_output(self) -> None:
        self._output_history.clear()
        if self._console_window is not None and self._console_window.winfo_exists():
            self._console_window.clear()

    def run_command(self) -> None:
        if self._worker is not None and self._worker.is_alive():
            messagebox.showinfo("Universe Simulator", "A command is already running.")
            return

        command = self._build_command()
        if command is None:
            return

        self._append_output("$ " + " ".join(command) + "\n")
        self._update_status("Running")
        self._worker = threading.Thread(target=self._execute_command, args=(command,), daemon=True)
        self._worker.start()
        self._set_control_states(True)
        self.after(100, self._drain_queue)

    def toggle_pause(self) -> None:
        process = self._process
        if process is None or process.poll() is not None:
            return
        if not hasattr(signal, "SIGSTOP") or os.name == "nt":
            messagebox.showinfo(
                "Universe Simulator",
                "Pause and resume are only available on Unix-like systems.",
            )
            return
        try:
            if self._paused:
                os.kill(process.pid, signal.SIGCONT)
                self._paused = False
                self.pause_button_text.set("Pause")
                self._append_output("[process resumed]\n")
                self._update_status("Running")
            else:
                os.kill(process.pid, signal.SIGSTOP)
                self._paused = True
                self.pause_button_text.set("Resume")
                self._append_output("[process paused]\n")
                self._update_status("Paused")
        except OSError as exc:
            messagebox.showerror("Universe Simulator", f"Unable to toggle pause: {exc}")

    def stop_command(self) -> None:
        process = self._process
        if process is None:
            self._update_status("Idle")
            return
        if process.poll() is None:
            self._append_output("[stopping process]\n")
            self._update_status("Stopping")
            try:
                process.terminate()
                process.wait(timeout=2)
            except subprocess.TimeoutExpired:
                process.kill()
            except OSError as exc:
                self._append_output(f"Failed to stop process: {exc}\n")
        self._process = None
        self._paused = False
        self.pause_button_text.set("Pause")
        self._set_control_states(False)

    def reset_interface(self) -> None:
        process = self._process
        if process is not None and process.poll() is None:
            self.stop_command()
            # Give the worker loop a moment to emit its exit notice before clearing.
            self.after(50, self.reset_interface)
            return

        self.mode.set("run_once")
        self.steps.set("1")
        self.delta.set("60")
        self.loop_interval.set("2.0")
        self.auto_steps.set("1")
        self.tick_delay.set("2.0")
        self.seed.set("")
        self.galaxies.set("")
        self.systems_per_galaxy.set("")
        self.planets_per_system.set("")
        self.workers.set(str(max(1, os.cpu_count() or 1)))
        self.php_binary.set("php")
        self.socket_path.set(str(self.project_root / "runtime" / "universe.sock"))
        self.pid_file_path.set(str(self.project_root / "runtime" / "universe.pid"))
        self.foreground.set(True)
        self.catalog_refresh_seconds.set(10.0)
        if hasattr(self, "refresh_spin"):
            self.refresh_spin.delete(0, tk.END)
            self.refresh_spin.insert(0, f"{self.catalog_refresh_seconds.get():.0f}")
        self._update_refresh_label(str(self.catalog_refresh_seconds.get()))
        self.auto_refresh.set(False)
        self._cancel_catalog_refresh()
        self.catalog_data = {}
        self._catalog_index.clear()
        self.catalog_search.set("")
        if hasattr(self, "catalog_tree"):
            for item in self.catalog_tree.get_children():
                self.catalog_tree.delete(item)
        if hasattr(self, "detail_views"):
            for view in self.detail_views.values():
                view.configure(state=tk.NORMAL)
                view.delete("1.0", tk.END)
                view.configure(state=tk.DISABLED)
        if hasattr(self, "visual_canvas"):
            self.visual_canvas.delete("all")
        self.clear_output()
        self._set_control_states(False)
        self._update_status("Idle")

    def _set_control_states(self, running: bool) -> None:
        if running:
            self.pause_button_text.set("Pause")
            if self.pause_button is not None:
                self.pause_button.configure(state=tk.NORMAL)
            if self.stop_button is not None:
                self.stop_button.configure(state=tk.NORMAL)
        else:
            self.pause_button_text.set("Pause")
            if self.pause_button is not None:
                self.pause_button.configure(state=tk.DISABLED)
            if self.stop_button is not None:
                self.stop_button.configure(state=tk.DISABLED)
            self._paused = False

    def _on_process_exit(self, _return_code: int) -> None:
        self._worker = None
        self._process = None
        self._paused = False
        self._set_control_states(False)
        if _return_code == 0:
            self._update_status("Completed", auto_reset=True)
        else:
            self._update_status(f"Error ({_return_code})")

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
        if workers := self.workers.get().strip():
            args.append(f"--workers={workers}")
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
            self._output_queue.put(("__STATUS__", "Error starting command", True))
            self._output_queue.put(("__EXIT__", 1))
            return

        self._process = process
        self._paused = False
        assert process.stdout is not None
        for line in process.stdout:
            self._output_queue.put(line)
        return_code = process.wait()
        self._output_queue.put(f"\n[process exited with code {return_code}]\n")
        self._output_queue.put(("__EXIT__", return_code))

    def _append_output(self, text: str) -> None:
        self._output_history.append(text)
        if len(self._output_history) > 2000:
            self._output_history = self._output_history[-2000:]
        window = self._console_window
        if window is not None and window.winfo_exists():
            window.append(text)

    def _drain_queue(self) -> None:
        while True:
            try:
                item = self._output_queue.get_nowait()
            except queue.Empty:
                break
            if isinstance(item, tuple) and item:
                tag = item[0]
                if tag == "__EXIT__":
                    try:
                        code = int(item[1])
                    except (IndexError, ValueError, TypeError):
                        code = 0
                    self._on_process_exit(code)
                    continue
                if tag == "__STATUS__":
                    message = str(item[1]) if len(item) > 1 else ""
                    auto_reset = bool(item[2]) if len(item) > 2 else False
                    if message:
                        self._update_status(message, auto_reset=auto_reset)
                    continue
            self._append_output(str(item))
        if self._worker is not None and self._worker.is_alive():
            self.after(100, self._drain_queue)

    def load_catalog(self, silent: bool = False) -> None:
        if self._catalog_worker is not None and self._catalog_worker.is_alive():
            if not silent:
                messagebox.showinfo(
                    "Universe Simulator",
                    "A catalog load is already in progress.",
                )
            return
        command = self._build_catalog_command()
        if command is None:
            return
        if not silent:
            self._append_output("$ " + " ".join(command) + "\n")
        self._catalog_worker = threading.Thread(
            target=self._run_catalog_command, args=(command, silent), daemon=True
        )
        self._catalog_worker.start()
        self.after(100, self._drain_catalog_queue)
        self._update_status("Loading catalog...")

    def _run_catalog_command(self, command: List[str], silent: bool) -> None:
        try:
            result = subprocess.run(
                command,
                check=True,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                cwd=self.project_root,
            )
            stdout = result.stdout or ""
            stderr = result.stderr or ""
            self._catalog_queue.put(("success", stdout, stderr, silent))
        except subprocess.CalledProcessError as exc:
            message = (exc.stderr or "").strip() or str(exc)
            stdout = exc.stdout or ""
            stderr = exc.stderr or ""
            self._catalog_queue.put(("error", message, stdout, stderr, silent))
        except Exception as exc:
            self._catalog_queue.put(("exception", str(exc), silent))

    def _handle_catalog_success(self, stdout: str, stderr: str, silent: bool) -> None:
        output = stdout.strip()
        if stderr and not silent:
            self._append_output(stderr)
        data = self._parse_catalog_output(output)
        if data is None:
            preview = output[:500]
            if silent:
                self._append_output("Unable to parse catalog output.\n")
            else:
                messagebox.showerror(
                    "Universe Simulator",
                    "Unable to parse catalog output. See console for details.",
                )
            if preview:
                self._append_output("Catalog stdout preview:\n" + preview + "\n")
            if stderr:
                self._append_output("Catalog stderr:\n" + stderr + "\n")
            self._update_status("Catalog error", auto_reset=True)
            return
        self.catalog_data = data
        self._populate_catalog_tree(data)
        if not silent:
            self._append_output("Catalog loaded.\n")
        self._update_status("Catalog loaded", auto_reset=True)

    def _drain_catalog_queue(self) -> None:
        while True:
            try:
                item = self._catalog_queue.get_nowait()
            except queue.Empty:
                break
            tag = item[0]
            if tag == "success":
                _, stdout, stderr, silent = item
                self._handle_catalog_success(stdout, stderr, silent)
            elif tag == "error":
                _, message, stdout, stderr, silent = item
                if silent:
                    self._append_output(f"Catalog command failed: {message}\n")
                else:
                    messagebox.showerror("Universe Simulator", f"Catalog command failed: {message}")
                preview = (stdout or "")[:500]
                if preview:
                    self._append_output("Catalog stdout preview:\n" + preview + "\n")
                if stderr:
                    self._append_output("Catalog stderr:\n" + stderr + "\n")
                self._update_status("Catalog error", auto_reset=True)
            elif tag == "exception":
                _, message, silent = item
                if silent:
                    self._append_output(f"Catalog command failed: {message}\n")
                else:
                    messagebox.showerror("Universe Simulator", f"Catalog command failed: {message}")
                self._update_status("Catalog error", auto_reset=True)
        worker = self._catalog_worker
        if worker is not None and worker.is_alive():
            self.after(100, self._drain_catalog_queue)
        else:
            self._catalog_worker = None

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
        if workers := self.workers.get().strip():
            args.append(f"--workers={workers}")
        return args

    def _parse_catalog_output(self, output: str) -> Dict[str, Any] | None:
        if not output:
            return None
        decoder = json.JSONDecoder()
        # First attempt: parse the full payload quickly.
        try:
            return decoder.decode(output)
        except json.JSONDecodeError:
            pass

        # If logging noise or other text surrounds the JSON, walk the string and
        # attempt a raw decode starting at each potential JSON boundary.
        index = 0
        length = len(output)
        while index < length:
            char = output[index]
            if char.isspace():
                index += 1
                continue
            if char not in "[{":
                index += 1
                continue
            try:
                obj, end = decoder.raw_decode(output[index:])
            except json.JSONDecodeError:
                index += 1
                continue
            if isinstance(obj, dict):
                return obj
            if isinstance(obj, list) and obj:
                # Catalogs should be mapping-based, but accept a top-level list
                # if one is provided and wrap it for callers.
                return {"items": obj}
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
        paned = ttk.PanedWindow(parent, orient=tk.HORIZONTAL)
        paned.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=12, pady=(12, 12))

        tree_container = ttk.Frame(paned, style="App.TFrame")
        detail_container = ttk.Frame(paned, style="App.TFrame")
        paned.add(tree_container, weight=1)
        paned.add(detail_container, weight=3)

        columns = ("summary",)
        self.catalog_tree = ttk.Treeview(
            tree_container,
            columns=columns,
            show="tree headings",
            style="Catalog.Treeview",
        )
        self.catalog_tree.heading("#0", text="Object")
        self.catalog_tree.heading("summary", text="Summary")
        self.catalog_tree.column("#0", width=260)
        self.catalog_tree.column("summary", width=360)
        self.catalog_tree.bind("<<TreeviewSelect>>", self._on_catalog_select)
        tree_scroll = ttk.Scrollbar(tree_container, orient=tk.VERTICAL, command=self.catalog_tree.yview)
        self.catalog_tree.configure(yscrollcommand=tree_scroll.set)
        self.catalog_tree.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        tree_scroll.pack(side=tk.RIGHT, fill=tk.Y)

        self.detail_notebook = ttk.Notebook(detail_container, style="Detail.TNotebook")
        self.detail_notebook.pack(side=tk.TOP, fill=tk.BOTH, expand=True)

        visual_tab = ttk.Frame(self.detail_notebook, style="App.TFrame")
        self.detail_notebook.add(visual_tab, text="Visual")
        self.visual_canvas = tk.Canvas(
            visual_tab,
            height=220,
            background="#0b0f1a",
            highlightthickness=0,
        )
        self.visual_canvas.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=8, pady=8)

        overview_tab = ttk.Frame(self.detail_notebook, style="App.TFrame")
        chronicle_tab = ttk.Frame(self.detail_notebook, style="App.TFrame")
        metadata_tab = ttk.Frame(self.detail_notebook, style="App.TFrame")
        raw_tab = ttk.Frame(self.detail_notebook, style="App.TFrame")

        self.detail_notebook.add(overview_tab, text="Overview")
        self.detail_notebook.add(chronicle_tab, text="Chronicle")
        self.detail_notebook.add(metadata_tab, text="Metadata")
        self.detail_notebook.add(raw_tab, text="Raw JSON")

        self.detail_views: Dict[str, tk.Text] = {}
        self.detail_views["overview"] = self._create_detail_text(overview_tab)
        self.detail_views["chronicle"] = self._create_detail_text(chronicle_tab)
        self.detail_views["metadata"] = self._create_detail_text(metadata_tab)
        self.detail_views["raw"] = self._create_detail_text(raw_tab, monospace=True)

    def _create_detail_text(self, parent: ttk.Frame, *, monospace: bool = False) -> tk.Text:
        widget = tk.Text(
            parent,
            wrap=tk.WORD,
            state=tk.DISABLED,
            background="#111827",
            foreground="#e2e8f0",
            borderwidth=0,
            highlightthickness=0,
            relief=tk.FLAT,
        )
        widget.pack(side=tk.TOP, fill=tk.BOTH, expand=True, padx=8, pady=8)
        font_name = "TkFixedFont" if monospace else "TkDefaultFont"
        widget.configure(font=(font_name, 10))
        return widget

    def _update_detail_view(self, key: str, content: str) -> None:
        view = self.detail_views.get(key)
        if view is None:
            return
        view.configure(state=tk.NORMAL)
        view.delete("1.0", tk.END)
        if content:
            view.insert(tk.END, content)
        view.configure(state=tk.DISABLED)

    def show_console(self) -> None:
        window = self._console_window
        if window is None or not window.winfo_exists():
            window = ConsoleWindow(self)
            self._console_window = window
            window.load_history(self._output_history)
        else:
            window.deiconify()
            window.lift()
            window.focus_force()

    def _detach_console(self, console: "ConsoleWindow") -> None:
        if self._console_window is console:
            self._console_window = None

    def _show_help_dialog(self) -> None:
        messagebox.showinfo(
            "Universe Browser",
            "Use the toolbar to load catalogs, adjust auto-refresh cadence, and open the simulation console.\n"
            "Select entries in the navigator to explore detailed lore, telemetry, and imagery without interfering "
            "with the simulation control panel.",
        )

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
        if self.catalog_search.get().strip():
            self._perform_catalog_search()

    def _insert_catalog_node(self, parent: str, node: Dict[str, Any]) -> str:
        name = str(node.get("name", "Unnamed"))
        summary = str(node.get("summary", ""))
        item_id = self.catalog_tree.insert(parent, tk.END, text=name, values=(summary,))
        self._catalog_index[item_id] = node
        for child in node.get("children", []):
            if isinstance(child, dict):
                self._insert_catalog_node(item_id, child)
        return item_id

    def _perform_catalog_search(self, _event: object | None = None) -> None:
        if not hasattr(self, "catalog_tree"):
            return
        query = self.catalog_search.get().strip().lower()
        if not query:
            return
        for item_id, node in self._catalog_index.items():
            name = str(node.get("name", "")).lower()
            summary = str(node.get("summary", "")).lower()
            if query in name or (summary and query in summary):
                self.catalog_tree.see(item_id)
                self.catalog_tree.selection_set(item_id)
                self.catalog_tree.focus(item_id)
                return
        messagebox.showinfo("Universe Browser", f"No catalog entries matching '{self.catalog_search.get()}'.")

    def _clear_catalog_search(self) -> None:
        self.catalog_search.set("")
        if hasattr(self, "catalog_tree"):
            selection = self.catalog_tree.selection()
            if selection:
                self.catalog_tree.see(selection[0])

    def _update_status(self, text: str, *, auto_reset: bool = False) -> None:
        self.status_text.set(text)
        tone_map = {
            "idle": "#e0e0e0",
            "running": "#81c784",
            "paused": "#ffb74d",
            "completed": "#64b5f6",
            "stopping": "#ef9a9a",
            "error": "#ef5350",
        }
        token = text.split(" ", 1)[0].lower() if text else "idle"
        color = tone_map.get(token, "#e0e0e0")
        if hasattr(self, "status_label"):
            self.status_label.configure(foreground=color)
        self._cancel_status_reset()
        if auto_reset:
            self._status_reset_job = self.after(5000, lambda: self._update_status("Idle"))

    def _cancel_status_reset(self) -> None:
        if self._status_reset_job is not None:
            try:
                self.after_cancel(self._status_reset_job)
            except Exception:
                pass
            self._status_reset_job = None

    def _on_catalog_select(self, _: object) -> None:
        selection = self.catalog_tree.selection()
        if not selection:
            return
        node = self._catalog_index.get(selection[0])
        if node is None:
            return
        self._display_catalog_details(node)

    def _display_catalog_details(self, node: Dict[str, Any]) -> None:
        overview_lines: List[str] = []
        category = str(node.get('category', 'object')).title()
        name = str(node.get('name', 'Unnamed'))
        overview_lines.append(f"{category}: {name}")
        if node.get('summary'):
            overview_lines.append(str(node['summary']))
        if node.get('description'):
            overview_lines.append("")
            overview_lines.append(str(node['description']))

        stats = node.get('statistics')
        if isinstance(stats, dict) and stats:
            overview_lines.append("")
            overview_lines.append("Statistics:")
            for key, value in stats.items():
                if key == "life_breakdown" and isinstance(value, dict):
                    overview_lines.append("  • Life Breakdown:")
                    overview_lines.extend(self._format_life_breakdown(value))
                    continue
                overview_lines.append(f"  • {key.replace('_', ' ').title()}: {self._format_statistic(key, value)}")

        chronicle_lines: List[str] = []
        chronicle = node.get('chronicle')
        if isinstance(chronicle, list) and chronicle:
            chronicle_lines.append(f"Recent moments for {name}:")
            for entry in chronicle[-12:]:
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
                    chronicle_lines.append(f"  • [{label}] {text}{suffix}")
                else:
                    chronicle_lines.append(f"  • {entry}")
        else:
            chronicle_lines.append("No chronicle entries recorded yet.")

        metadata_lines: List[str] = []
        metadata = node.get('metadata')
        if isinstance(metadata, dict) and metadata:
            metadata_lines.append("Node metadata:")
            for key, value in metadata.items():
                if key == "map" and isinstance(value, dict):
                    metadata_lines.append(f"  • Map: {self._summarize_map_metadata(value)}")
                    continue
                if key == "territory" and isinstance(value, dict):
                    metadata_lines.append(f"  • Territory: {self._summarize_territory(value)}")
                    continue
                metadata_lines.append(f"  • {key.replace('_', ' ').title()}: {self._format_value(value)}")
        else:
            metadata_lines.append("No supplemental metadata available.")

        image_info = node.get('image')
        if isinstance(image_info, dict):
            image_meta = image_info.get('metadata')
            if isinstance(image_meta, dict) and image_meta:
                metadata_lines.append("")
                metadata_lines.append("Image metadata:")
                for key, value in image_meta.items():
                    metadata_lines.append(f"  • {key.replace('_', ' ').title()}: {self._format_value(value)}")

        overview_text = "\n".join(line for line in overview_lines if line is not None).strip()
        chronicle_text = "\n".join(chronicle_lines).strip()
        metadata_text = "\n".join(metadata_lines).strip()
        raw_text = json.dumps(node, indent=2, ensure_ascii=False)

        self._update_detail_view("overview", overview_text)
        self._update_detail_view("chronicle", chronicle_text)
        self._update_detail_view("metadata", metadata_text)
        self._update_detail_view("raw", raw_text)

        self._render_visual(node)

    def _format_value(self, value: Any) -> str:
        if isinstance(value, (dict, list)):
            text = json.dumps(value, indent=2)
            if len(text) > 800:
                return text[:797] + "..."
            return text
        return str(value)

    def _format_statistic(self, key: str, value: Any) -> str:
        if isinstance(value, (int, float)):
            if abs(value - int(value)) < 1e-6:
                return f"{int(value):,}"
            return f"{value:,.2f}"
        return self._format_value(value)

    def _format_life_breakdown(self, breakdown: Dict[str, Any]) -> List[str]:
        lines: List[str] = []
        kingdoms = breakdown.get("kingdoms")
        if isinstance(kingdoms, dict) and kingdoms:
            lines.append("    Kingdoms:")
            for name, percent in list(kingdoms.items())[:5]:
                try:
                    pct = float(percent)
                except (TypeError, ValueError):
                    pct = 0.0
                lines.append(f"      • {name}: {pct:.2f}%")
        phyla = breakdown.get("phyla")
        if isinstance(phyla, dict) and phyla:
            lines.append("    Phyla:")
            for name, percent in list(phyla.items())[:5]:
                try:
                    pct = float(percent)
                except (TypeError, ValueError):
                    pct = 0.0
                lines.append(f"      • {name}: {pct:.2f}%")
        entries = breakdown.get("entries")
        if isinstance(entries, list) and entries and len(lines) < 2:
            for entry in entries[:5]:
                if not isinstance(entry, dict):
                    continue
                kingdom = entry.get('kingdom', 'Unknown')
                phylum = entry.get('phylum', 'Unclassified')
                try:
                    pct = float(entry.get('share', 0.0))
                except (TypeError, ValueError):
                    pct = 0.0
                lines.append(f"      • {kingdom} / {phylum}: {pct:.2f}%")
        return lines or ["    (no biosphere data)"]

    def _summarize_map_metadata(self, map_data: Dict[str, Any]) -> str:
        parts: List[str] = []
        countries = map_data.get('countries')
        cities = map_data.get('cities')
        residents = map_data.get('residents')
        if isinstance(countries, list):
            parts.append(f"{len(countries)} countries")
        if isinstance(cities, list):
            parts.append(f"{len(cities)} cities")
        if isinstance(residents, list):
            parts.append(f"{len(residents)} residents plotted")
        if 'coordinates' in map_data and not parts:
            coords = map_data.get('coordinates') or {}
            lat = float(coords.get('latitude', 0.0))
            lon = float(coords.get('longitude', coords.get('lon', 0.0)))
            parts.append(f"center at {lat:.1f}°, {lon:.1f}°")
        return ", ".join(parts) if parts else "available"

    def _summarize_territory(self, territory: Dict[str, Any]) -> str:
        center = territory.get('center') or {}
        span = territory.get('span') or {}
        try:
            lat = float(center.get('latitude', center.get('lat', 0.0)))
            lon = float(center.get('longitude', center.get('lon', 0.0)))
        except (TypeError, ValueError):
            lat = 0.0
            lon = 0.0
        try:
            lat_span = float(span.get('latitude', 0.0))
            lon_span = float(span.get('longitude', 0.0))
        except (TypeError, ValueError):
            lat_span = 0.0
            lon_span = 0.0
        biome = territory.get('biome')
        terrain = territory.get('terrain')
        descriptor = f"center=({lat:.1f}°, {lon:.1f}°), span≈({lat_span:.1f}°, {lon_span:.1f}°)"
        if biome or terrain:
            extras = []
            if biome:
                extras.append(str(biome))
            if terrain:
                extras.append(str(terrain))
            descriptor += f" | {'; '.join(extras)}"
        return descriptor

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
        metadata = node.get('metadata') if isinstance(node.get('metadata'), dict) else {}

        self._visual_base_image = None
        self._visual_display_image = None
        image_info = node.get('image') if isinstance(node.get('image'), dict) else None
        display_image = None
        if isinstance(image_info, dict):
            raw_data = image_info.get('data')
            if isinstance(raw_data, str) and raw_data:
                try:
                    decoded = base64.b64decode(raw_data)
                    encoded = base64.b64encode(decoded).decode('ascii')
                    base_image = tk.PhotoImage(data=encoded)
                    display_image = base_image
                    scale_x = base_image.width() / max(width - 16, 1)
                    scale_y = base_image.height() / max(height - 16, 1)
                    reduction = max(scale_x, scale_y)
                    if reduction > 1.0:
                        factor = int(math.ceil(reduction))
                        display_image = base_image.subsample(factor, factor)
                    self._visual_base_image = base_image
                    self._visual_display_image = display_image
                except (base64.binascii.Error, ValueError, tk.TclError):
                    self._visual_base_image = None
                    self._visual_display_image = None

        map_priority = category in {'country', 'city', 'person'}
        if display_image is not None and not map_priority:
            canvas.create_image(width // 2, height // 2, image=display_image)
            self._draw_visual_overlay(canvas, image_info, width, height)
            if category == 'planet':
                self._draw_planet_overlay(canvas, node, width, height)
            return

        color_map = {
            'universe': '#2f80ed',
            'galaxy': '#bb86fc',
            'system': '#03dac6',
            'star': '#fdd663',
            'planet': '#8ab4f8',
            'country': '#a5d6a7',
            'city': '#f48fb1',
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
            self._draw_planet_overlay(canvas, node, width, height)
        elif category == 'star':
            canvas.create_oval(cx - radius, cy - radius, cx + radius, cy + radius, fill=color, outline="")
        elif category == 'country':
            self._draw_country_map(canvas, metadata, width, height)
        elif category == 'city':
            self._draw_city_map(canvas, metadata, width, height)
        elif category == 'person':
            self._draw_person_marker(canvas, node, width, height, color)
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

        if isinstance(image_info, dict):
            self._draw_visual_overlay(canvas, image_info, width, height)

    def _draw_visual_overlay(self, canvas: tk.Canvas, image_info: Dict[str, Any], width: int, height: int) -> None:
        metadata = image_info.get('metadata') if isinstance(image_info, dict) else None
        if not isinstance(metadata, dict):
            return
        lines: List[str] = []
        generator = metadata.get('generator')
        if generator:
            lines.append(f"Generator: {generator}")
        prompt = metadata.get('prompt')
        if prompt:
            lines.append(f"Prompt: {prompt}")
        if metadata.get('width') and metadata.get('height'):
            try:
                width_val = int(metadata.get('width'))
                height_val = int(metadata.get('height'))
                lines.append(f"Resolution: {width_val}×{height_val}")
            except (TypeError, ValueError):
                pass
        created = metadata.get('created_at')
        if isinstance(created, (int, float)):
            try:
                stamp = datetime.fromtimestamp(float(created)).isoformat(timespec='seconds')
                lines.append(f"Generated: {stamp}")
            except (OverflowError, OSError, ValueError):
                pass
        if not lines:
            return
        padding = 10
        text_block = '\n'.join(lines)
        rect_height = (len(lines) * 16) + padding
        canvas.create_rectangle(
            padding,
            height - rect_height - padding,
            min(width - padding, width * 0.6),
            height - padding,
            fill="#1e293b",
            outline="",
        )
        canvas.create_text(
            padding + 6,
            height - padding - 6,
            anchor=tk.SW,
            text=text_block,
            fill="#cbd5f5",
            font=("TkDefaultFont", 9),
        )

    def _latlon_to_canvas(self, latitude: float, longitude: float, width: int, height: int) -> Tuple[float, float]:
        x = (longitude + 180.0) / 360.0 * width
        y = height - ((latitude + 90.0) / 180.0 * height)
        return x, y

    def _draw_country_map(self, canvas: tk.Canvas, metadata: Dict[str, Any], width: int, height: int) -> None:
        territory = metadata.get('territory') if isinstance(metadata, dict) else None
        if isinstance(territory, dict):
            center = territory.get('center') or {}
            span = territory.get('span') or {}
            try:
                lat = float(center.get('latitude', center.get('lat', 0.0)))
                lon = float(center.get('longitude', center.get('lon', 0.0)))
                lat_span = float(span.get('latitude', 0.0))
                lon_span = float(span.get('longitude', 0.0))
            except (TypeError, ValueError):
                lat = lon = lat_span = lon_span = 0.0
            top_lat = lat + (lat_span / 2.0)
            bottom_lat = lat - (lat_span / 2.0)
            left_lon = lon - (lon_span / 2.0)
            right_lon = lon + (lon_span / 2.0)
            x1, y1 = self._latlon_to_canvas(top_lat, left_lon, width, height)
            x2, y2 = self._latlon_to_canvas(bottom_lat, right_lon, width, height)
            canvas.create_rectangle(x1, y1, x2, y2, outline="#64ffda", width=2)

        city_markers = metadata.get('map', {}).get('cities') if isinstance(metadata, dict) else None
        if isinstance(city_markers, list):
            for city in city_markers:
                if not isinstance(city, dict):
                    continue
                coords = city.get('coordinates') or {}
                try:
                    lat = float(coords.get('latitude', coords.get('lat', 0.0)))
                    lon = float(coords.get('longitude', coords.get('lon', 0.0)))
                except (TypeError, ValueError):
                    continue
                x, y = self._latlon_to_canvas(lat, lon, width, height)
                canvas.create_oval(x - 4, y - 4, x + 4, y + 4, fill="#ffab91", outline="")
                name = str(city.get('name', ''))
                if name:
                    canvas.create_text(x + 6, y, text=name[:24], anchor=tk.W, fill="#f8bbd0")

    def _draw_city_map(self, canvas: tk.Canvas, metadata: Dict[str, Any], width: int, height: int) -> None:
        if not isinstance(metadata, dict):
            return
        map_data = metadata.get('map')
        if not isinstance(map_data, dict):
            return
        coords = map_data.get('coordinates') or {}
        try:
            lat = float(coords.get('latitude', coords.get('lat', 0.0)))
            lon = float(coords.get('longitude', coords.get('lon', 0.0)))
        except (TypeError, ValueError):
            lat = lon = 0.0
        try:
            radius_deg = float(map_data.get('radius', 10.0))
        except (TypeError, ValueError):
            radius_deg = 10.0
        center_x, center_y = self._latlon_to_canvas(lat, lon, width, height)
        pixel_radius = max(12.0, min(width, height) * min(0.45, abs(radius_deg) / 80.0))
        canvas.create_oval(
            center_x - pixel_radius,
            center_y - pixel_radius,
            center_x + pixel_radius,
            center_y + pixel_radius,
            outline="#64b5f6",
            width=2,
            fill="#102027",
        )
        residents = map_data.get('residents')
        if isinstance(residents, list):
            scale = pixel_radius / max(1.0, abs(radius_deg))
            for resident in residents[:200]:
                if not isinstance(resident, dict):
                    continue
                rcoords = resident.get('coordinates') or {}
                try:
                    rlat = float(rcoords.get('latitude', rcoords.get('lat', 0.0)))
                    rlon = float(rcoords.get('longitude', rcoords.get('lon', 0.0)))
                except (TypeError, ValueError):
                    continue
                rx = center_x + (rlon - lon) * scale
                ry = center_y - (rlat - lat) * scale
                canvas.create_oval(rx - 2, ry - 2, rx + 2, ry + 2, fill="#ffeb3b", outline="")
        canvas.create_oval(center_x - 4, center_y - 4, center_x + 4, center_y + 4, fill="#82b1ff", outline="")

    def _draw_person_marker(self, canvas: tk.Canvas, node: Dict[str, Any], width: int, height: int, color: str) -> None:
        stats = node.get('statistics') if isinstance(node.get('statistics'), dict) else {}
        coords = stats.get('coordinates') if isinstance(stats, dict) else None
        if isinstance(coords, dict):
            try:
                lat = float(coords.get('latitude', coords.get('lat', 0.0)))
                lon = float(coords.get('longitude', coords.get('lon', 0.0)))
            except (TypeError, ValueError):
                lat = lon = 0.0
            x, y = self._latlon_to_canvas(lat, lon, width, height)
            canvas.create_oval(x - 6, y - 6, x + 6, y + 6, outline=color, width=2)
            canvas.create_oval(x - 2, y - 2, x + 2, y + 2, fill=color, outline="")
            name = node.get('name', '')
            if name:
                canvas.create_text(x, y - 12, text=str(name)[:32], fill=color)
        else:
            cx, cy = width // 2, height // 2
            radius = min(width, height) // 3
            canvas.create_oval(cx - radius // 2, cy - radius, cx + radius // 2, cy - radius // 2, fill=color, outline="")
            canvas.create_rectangle(cx - radius // 3, cy - radius // 2, cx + radius // 3, cy + radius // 2, fill=color, outline="")

    def _draw_planet_overlay(self, canvas: tk.Canvas, node: Dict[str, Any], width: int, height: int) -> None:
        statistics = node.get('statistics') if isinstance(node.get('statistics'), dict) else {}
        breakdown = statistics.get('life_breakdown') if isinstance(statistics, dict) else None
        if isinstance(breakdown, dict):
            kingdoms = breakdown.get('kingdoms')
            if isinstance(kingdoms, dict) and kingdoms:
                y_offset = 20
                canvas.create_text(
                    width - 10,
                    y_offset,
                    anchor=tk.NE,
                    text="Dominant Kingdoms",
                    fill="#e0f7fa",
                    font=("TkDefaultFont", 10, "bold"),
                )
                for name, percent in list(kingdoms.items())[:4]:
                    try:
                        pct = float(percent)
                    except (TypeError, ValueError):
                        pct = 0.0
                    y_offset += 16
                    canvas.create_text(
                        width - 10,
                        y_offset,
                        anchor=tk.NE,
                        text=f"{name}: {pct:.1f}%",
                        fill="#e0f7fa",
                    )
        net_worth = statistics.get('net_worth') if isinstance(statistics, dict) else None
        if isinstance(net_worth, (int, float)):
            canvas.create_text(
                width // 2,
                height - 20,
                text=f"Net worth: {net_worth:,.0f}",
                fill="#ffe082",
            )


class ConsoleWindow(tk.Toplevel):
    def __init__(self, gui: UniverseGUI) -> None:
        super().__init__(gui)
        self.gui = gui
        self.title("Simulation Console")
        self.geometry("720x420")
        self.transient(gui)
        self.protocol("WM_DELETE_WINDOW", self._on_close)

        container = ttk.Frame(self, padding=12)
        container.pack(fill=tk.BOTH, expand=True)

        self.text = tk.Text(
            container,
            wrap=tk.WORD,
            state=tk.DISABLED,
            background="#0b1120",
            foreground="#e2e8f0",
        )
        self.text.pack(side=tk.LEFT, fill=tk.BOTH, expand=True)
        scrollbar = ttk.Scrollbar(container, orient=tk.VERTICAL, command=self.text.yview)
        scrollbar.pack(side=tk.RIGHT, fill=tk.Y)
        self.text.configure(yscrollcommand=scrollbar.set)

        actions = ttk.Frame(self, padding=(12, 0, 12, 12))
        actions.pack(fill=tk.X)
        ttk.Button(actions, text="Clear", command=self.clear).pack(side=tk.LEFT)
        ttk.Button(actions, text="Close", command=self._on_close).pack(side=tk.RIGHT)

    def append(self, text: str) -> None:
        self.text.configure(state=tk.NORMAL)
        self.text.insert(tk.END, text)
        self.text.see(tk.END)
        self.text.configure(state=tk.DISABLED)

    def load_history(self, history: Iterable[str]) -> None:
        self.text.configure(state=tk.NORMAL)
        self.text.delete("1.0", tk.END)
        for chunk in history:
            self.text.insert(tk.END, chunk)
        self.text.see(tk.END)
        self.text.configure(state=tk.DISABLED)

    def clear(self) -> None:
        self.text.configure(state=tk.NORMAL)
        self.text.delete("1.0", tk.END)
        self.text.configure(state=tk.DISABLED)

    def _on_close(self) -> None:
        self.gui._detach_console(self)
        self.destroy()


class ControlPanel(tk.Toplevel):
    def __init__(self, gui: UniverseGUI) -> None:
        super().__init__(gui)
        self.gui = gui
        self.title("Simulation Control")
        self.resizable(False, False)
        self.transient(gui)
        self.protocol("WM_DELETE_WINDOW", self._on_close)

        container = ttk.Frame(self, padding=12)
        container.pack(fill=tk.BOTH, expand=True)

        binary_frame = ttk.Frame(container)
        binary_frame.pack(fill=tk.X)
        ttk.Label(binary_frame, text="PHP binary:").grid(row=0, column=0, sticky=tk.W, padx=(0, 6))
        ttk.Entry(binary_frame, textvariable=self.gui.php_binary, width=18).grid(row=0, column=1, sticky=tk.W)

        mode_frame = ttk.LabelFrame(container, text="Mode")
        mode_frame.pack(fill=tk.X, pady=(12, 0))
        ttk.Radiobutton(mode_frame, text="Run Once", value="run_once", variable=self.gui.mode).pack(anchor=tk.W, padx=8, pady=2)
        ttk.Radiobutton(mode_frame, text="Start Daemon", value="start", variable=self.gui.mode).pack(anchor=tk.W, padx=8, pady=2)

        run_once_frame = ttk.LabelFrame(container, text="Run Once Options")
        run_once_frame.pack(fill=tk.X, pady=(12, 0))
        ttk.Label(run_once_frame, text="Steps:").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.gui.steps, width=8).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(run_once_frame, text="Delta (s):").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.gui.delta, width=10).grid(row=0, column=3, sticky=tk.W, pady=4)
        ttk.Label(run_once_frame, text="Tick Delay (s):").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(run_once_frame, textvariable=self.gui.tick_delay, width=10).grid(row=1, column=1, sticky=tk.W, pady=4)

        daemon_frame = ttk.LabelFrame(container, text="Daemon Options")
        daemon_frame.pack(fill=tk.X, pady=(12, 0))
        ttk.Label(daemon_frame, text="Loop Interval (s):").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.gui.loop_interval, width=10).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(daemon_frame, text="Auto Steps:").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.gui.auto_steps, width=8).grid(row=0, column=3, sticky=tk.W, pady=4)
        ttk.Label(daemon_frame, text="Socket Path:").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.gui.socket_path, width=36).grid(row=1, column=1, columnspan=3, sticky=tk.EW, pady=4)
        ttk.Label(daemon_frame, text="PID File:").grid(row=2, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(daemon_frame, textvariable=self.gui.pid_file_path, width=36).grid(row=2, column=1, columnspan=3, sticky=tk.EW, pady=4)
        ttk.Checkbutton(daemon_frame, text="Stay in foreground", variable=self.gui.foreground).grid(row=3, column=0, columnspan=4, sticky=tk.W, padx=8, pady=(4, 4))

        generation_frame = ttk.LabelFrame(container, text="Generation Options")
        generation_frame.pack(fill=tk.X, pady=(12, 0))
        ttk.Label(generation_frame, text="Seed:").grid(row=0, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.gui.seed, width=12).grid(row=0, column=1, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Galaxies:").grid(row=0, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.gui.galaxies, width=10).grid(row=0, column=3, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Systems/Galaxy:").grid(row=1, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.gui.systems_per_galaxy, width=12).grid(row=1, column=1, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Planets/System:").grid(row=1, column=2, sticky=tk.W, padx=(12, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.gui.planets_per_system, width=12).grid(row=1, column=3, sticky=tk.W, pady=4)
        ttk.Label(generation_frame, text="Workers:").grid(row=2, column=0, sticky=tk.W, padx=(8, 6), pady=4)
        ttk.Entry(generation_frame, textvariable=self.gui.workers, width=12).grid(row=2, column=1, sticky=tk.W, pady=4)

        button_frame = ttk.Frame(container)
        button_frame.pack(fill=tk.X, pady=(16, 0))
        self.gui.run_button = ttk.Button(button_frame, text="Run", command=self.gui.run_command)
        self.gui.run_button.pack(side=tk.LEFT)
        self.gui.pause_button = ttk.Button(
            button_frame,
            textvariable=self.gui.pause_button_text,
            command=self.gui.toggle_pause,
            state=tk.DISABLED,
        )
        self.gui.pause_button.pack(side=tk.LEFT, padx=(8, 0))
        self.gui.stop_button = ttk.Button(button_frame, text="Stop", command=self.gui.stop_command, state=tk.DISABLED)
        self.gui.stop_button.pack(side=tk.LEFT, padx=(8, 0))
        ttk.Button(button_frame, text="Reset", command=self.gui.reset_interface).pack(side=tk.LEFT, padx=(8, 0))
        ttk.Button(button_frame, text="Clear Output", command=self.gui.clear_output).pack(side=tk.LEFT, padx=(8, 0))
        ttk.Button(button_frame, text="Load Catalog", command=self.gui.load_catalog).pack(side=tk.LEFT, padx=(24, 0))
        ttk.Button(button_frame, text="Close", command=self._on_close).pack(side=tk.RIGHT)

        self.gui._set_control_states(self.gui._worker is not None and self.gui._worker.is_alive())

    def _on_close(self) -> None:
        self.gui.close_control_panel(self)
        self.destroy()


def main() -> None:
    gui = UniverseGUI()
    gui.mainloop()


if __name__ == "__main__":
    main()
