import tkinter as tk
from tkinter import ttk, filedialog, messagebox
import zlib
import json
import os
import threading
from pathlib import Path

# ── Target filenames ──────────────────────────────────────────────────────────
TARGET_FILES = [
    "skills", "library", "enemy", "npc", "pet", "mission",
    "gamedata", "talents", "senjutsu", "skill-effect", "weapon-effect",
    "back_item-effect", "accessory-effect", "arena-effect", "animation",
]

# ── Colours & fonts ───────────────────────────────────────────────────────────
BG        = "#0d0f1a"
PANEL     = "#13162a"
ACCENT    = "#00e5ff"
ACCENT2   = "#ff4b6e"
TEXT      = "#e8eaf6"
MUTED     = "#5c6080"
SUCCESS   = "#00e676"
WARNING   = "#ffab40"
ERROR     = "#ff1744"
FONT_MAIN = ("Courier New", 10)
FONT_HEAD = ("Courier New", 13, "bold")
FONT_MONO = ("Courier New", 9)


# ── Conversion helpers ────────────────────────────────────────────────────────
def json_to_bin(src: Path, dst: Path) -> tuple[bool, str]:
    try:
        with open(src, "r", encoding="utf-8") as f:
            data = json.load(f)
        raw = json.dumps(data, separators=(",", ":")).encode("utf-8")
        compressed = zlib.compress(raw, level=zlib.Z_BEST_COMPRESSION)
        with open(dst, "wb") as f:
            f.write(compressed)
        ratio = 100 * len(compressed) / len(raw)
        return True, f"{len(raw):,} → {len(compressed):,} bytes  ({ratio:.1f}%)"
    except Exception as e:
        return False, str(e)


def bin_to_json(src: Path, dst: Path) -> tuple[bool, str]:
    try:
        with open(src, "rb") as f:
            raw = f.read()
        try:
            data_bytes = zlib.decompress(raw)
        except zlib.error:
            data_bytes = raw
        try:
            data = json.loads(data_bytes)
        except json.JSONDecodeError as e:
            return False, f"JSON decode error: {e}"
        with open(dst, "w", encoding="utf-8") as f:
            json.dump(data, f, indent=4)
        return True, f"{len(raw):,} bytes → {len(data_bytes):,} bytes (decompressed)"
    except Exception as e:
        return False, str(e)


# ── Main App ──────────────────────────────────────────────────────────────────
class ConverterApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("GameData File Converter")
        self.configure(bg=BG)
        self.resizable(True, True)
        self.minsize(760, 540)

        self.folder_var  = tk.StringVar()
        self.mode_var    = tk.StringVar(value="json_to_bin")
        self.status_rows = []   # list of (name, label_status, label_info)

        self._build_ui()
        self.update_idletasks()
        w, h = 860, 660
        x = (self.winfo_screenwidth()  - w) // 2
        y = (self.winfo_screenheight() - h) // 2
        self.geometry(f"{w}x{h}+{x}+{y}")

    # ── UI construction ───────────────────────────────────────────────────────
    def _build_ui(self):
        # ── Header ──
        hdr = tk.Frame(self, bg=BG)
        hdr.pack(fill="x", padx=24, pady=(20, 0))

        tk.Label(hdr, text="◈ GAMEDATA", font=("Courier New", 22, "bold"),
                 fg=ACCENT, bg=BG).pack(side="left")
        tk.Label(hdr, text=" FILE CONVERTER", font=("Courier New", 22, "bold"),
                 fg=TEXT, bg=BG).pack(side="left")

        divider = tk.Frame(self, bg=ACCENT, height=1)
        divider.pack(fill="x", padx=24, pady=(8, 16))

        # ── Mode selector ──
        mode_frame = tk.Frame(self, bg=BG)
        mode_frame.pack(fill="x", padx=24, pady=(0, 12))

        tk.Label(mode_frame, text="MODE", font=("Courier New", 9, "bold"),
                 fg=MUTED, bg=BG).pack(side="left", padx=(0, 12))

        self._radio(mode_frame, "JSON  →  BIN", "json_to_bin")
        self._radio(mode_frame, "BIN  →  JSON", "bin_to_json")

        # ── Folder picker ──
        folder_frame = tk.Frame(self, bg=PANEL, bd=0, relief="flat")
        folder_frame.pack(fill="x", padx=24, pady=(0, 14))

        tk.Label(folder_frame, text=" FOLDER ", font=("Courier New", 9, "bold"),
                 fg=MUTED, bg=PANEL).pack(side="left", padx=(10, 4), pady=10)

        self.folder_entry = tk.Entry(folder_frame, textvariable=self.folder_var,
                                     font=FONT_MONO, bg="#1a1e38", fg=TEXT,
                                     insertbackground=ACCENT, relief="flat",
                                     bd=0, highlightthickness=1,
                                     highlightbackground=MUTED,
                                     highlightcolor=ACCENT)
        self.folder_entry.pack(side="left", fill="x", expand=True,
                               padx=4, pady=8, ipady=4)

        tk.Button(folder_frame, text="BROWSE", command=self._browse,
                  font=("Courier New", 9, "bold"), bg=ACCENT, fg=BG,
                  relief="flat", bd=0, padx=12, cursor="hand2",
                  activebackground="#40f0ff", activeforeground=BG
                  ).pack(side="left", padx=(4, 10), pady=8)

        # ── File list ──
        list_lbl = tk.Frame(self, bg=BG)
        list_lbl.pack(fill="x", padx=24)
        tk.Label(list_lbl, text="TARGET FILES", font=("Courier New", 9, "bold"),
                 fg=MUTED, bg=BG).pack(side="left")
        self.found_lbl = tk.Label(list_lbl, text="", font=FONT_MONO,
                                  fg=ACCENT2, bg=BG)
        self.found_lbl.pack(side="right")

        scroll_outer = tk.Frame(self, bg=PANEL, bd=0)
        scroll_outer.pack(fill="both", expand=True, padx=24, pady=(4, 0))

        canvas = tk.Canvas(scroll_outer, bg=PANEL, bd=0,
                           highlightthickness=0)
        sb = ttk.Scrollbar(scroll_outer, orient="vertical",
                           command=canvas.yview)
        canvas.configure(yscrollcommand=sb.set)

        sb.pack(side="right", fill="y")
        canvas.pack(side="left", fill="both", expand=True)

        self.list_frame = tk.Frame(canvas, bg=PANEL)
        self.list_window = canvas.create_window(
            (0, 0), window=self.list_frame, anchor="nw")

        self.list_frame.bind("<Configure>",
            lambda e: canvas.configure(
                scrollregion=canvas.bbox("all")))
        canvas.bind("<Configure>",
            lambda e: canvas.itemconfig(
                self.list_window, width=e.width))
        canvas.bind_all("<MouseWheel>",
            lambda e: canvas.yview_scroll(-1*(e.delta//120), "units"))

        self._build_file_rows()

        # ── Bottom bar ──
        bot = tk.Frame(self, bg=BG)
        bot.pack(fill="x", padx=24, pady=12)

        self.progress = ttk.Progressbar(bot, mode="determinate",
                                        style="Accent.Horizontal.TProgressbar")
        self.progress.pack(fill="x", pady=(0, 8))

        self.convert_btn = tk.Button(
            bot, text="▶  CONVERT ALL", command=self._start_conversion,
            font=("Courier New", 11, "bold"), bg=ACCENT2, fg="white",
            relief="flat", bd=0, padx=20, pady=10, cursor="hand2",
            activebackground="#ff7090", activeforeground="white")
        self.convert_btn.pack(side="left")

        self.summary_lbl = tk.Label(bot, text="", font=FONT_MONO,
                                    fg=MUTED, bg=BG)
        self.summary_lbl.pack(side="right", padx=8)

        # ttk style
        style = ttk.Style(self)
        style.theme_use("default")
        style.configure("Accent.Horizontal.TProgressbar",
                        troughcolor=PANEL, background=ACCENT,
                        bordercolor=PANEL, lightcolor=ACCENT,
                        darkcolor=ACCENT)

    def _radio(self, parent, label, value):
        f = tk.Frame(parent, bg=BG)
        f.pack(side="left", padx=8)
        rb = tk.Radiobutton(f, text=label, variable=self.mode_var,
                            value=value, font=("Courier New", 10, "bold"),
                            fg=TEXT, bg=BG, selectcolor=BG,
                            activebackground=BG, activeforeground=ACCENT,
                            indicatoron=False, relief="flat",
                            padx=14, pady=6, cursor="hand2",
                            command=self._on_mode_change)
        rb.pack()
        # highlight selected
        def refresh(*_):
            if self.mode_var.get() == value:
                rb.config(fg=BG, bg=ACCENT)
            else:
                rb.config(fg=TEXT, bg=PANEL)
        self.mode_var.trace_add("write", refresh)
        refresh()
        return rb

    def _build_file_rows(self):
        for w in self.list_frame.winfo_children():
            w.destroy()
        self.status_rows.clear()

        # header row
        hrow = tk.Frame(self.list_frame, bg=PANEL)
        hrow.pack(fill="x", padx=6, pady=(6, 2))
        tk.Label(hrow, text="FILE", font=("Courier New", 8, "bold"),
                 fg=MUTED, bg=PANEL, width=24, anchor="w").pack(side="left")
        tk.Label(hrow, text="STATUS", font=("Courier New", 8, "bold"),
                 fg=MUTED, bg=PANEL, width=10, anchor="w").pack(side="left")
        tk.Label(hrow, text="INFO", font=("Courier New", 8, "bold"),
                 fg=MUTED, bg=PANEL, anchor="w").pack(side="left", fill="x", expand=True)

        tk.Frame(self.list_frame, bg=MUTED, height=1).pack(fill="x", padx=6, pady=2)

        for name in TARGET_FILES:
            row = tk.Frame(self.list_frame, bg=PANEL)
            row.pack(fill="x", padx=6, pady=1)

            tk.Label(row, text=f"  {name}", font=FONT_MONO, fg=TEXT,
                     bg=PANEL, width=24, anchor="w").pack(side="left")

            lbl_status = tk.Label(row, text="─", font=FONT_MONO,
                                  fg=MUTED, bg=PANEL, width=10, anchor="w")
            lbl_status.pack(side="left")

            lbl_info = tk.Label(row, text="", font=FONT_MONO,
                                fg=MUTED, bg=PANEL, anchor="w")
            lbl_info.pack(side="left", fill="x", expand=True)

            self.status_rows.append((name, lbl_status, lbl_info))

    def _on_mode_change(self):
        self._build_file_rows()
        self.found_lbl.config(text="")
        self.summary_lbl.config(text="")
        self.progress["value"] = 0
        folder = self.folder_var.get()
        if folder:
            self._scan_folder(folder)

    # ── Folder browsing ───────────────────────────────────────────────────────
    def _browse(self):
        folder = filedialog.askdirectory(title="Select Game Data Folder")
        if folder:
            self.folder_var.set(folder)
            self._scan_folder(folder)

    def _scan_folder(self, folder: str):
        mode = self.mode_var.get()
        ext_src = ".json" if mode == "json_to_bin" else ".bin"
        found = 0
        for name, lbl_status, lbl_info in self.status_rows:
            src = Path(folder) / f"{name}{ext_src}"
            if src.exists():
                found += 1
                lbl_status.config(text="FOUND", fg=SUCCESS)
                lbl_info.config(text=f"{src.stat().st_size:,} bytes", fg=MUTED)
            else:
                lbl_status.config(text="MISSING", fg=WARNING)
                lbl_info.config(text="", fg=MUTED)
        self.found_lbl.config(
            text=f"{found}/{len(TARGET_FILES)} files found")

    # ── Conversion ────────────────────────────────────────────────────────────
    def _start_conversion(self):
        folder = self.folder_var.get().strip()
        if not folder or not Path(folder).is_dir():
            messagebox.showerror("Error", "Please select a valid folder first.")
            return
        self.convert_btn.config(state="disabled")
        self.summary_lbl.config(text="Working…", fg=MUTED)
        threading.Thread(target=self._run_conversion,
                         args=(folder,), daemon=True).start()

    def _run_conversion(self, folder: str):
        mode    = self.mode_var.get()
        ext_src = ".json" if mode == "json_to_bin" else ".bin"
        ext_dst = ".bin"  if mode == "json_to_bin" else ".json"
        convert = json_to_bin if mode == "json_to_bin" else bin_to_json

        total   = len(TARGET_FILES)
        ok      = 0
        skipped = 0

        for i, (name, lbl_status, lbl_info) in enumerate(self.status_rows):
            src = Path(folder) / f"{name}{ext_src}"
            dst = Path(folder) / f"{name}{ext_dst}"

            if not src.exists():
                skipped += 1
                self._set_row(lbl_status, lbl_info, "SKIP", MUTED, "source not found")
            else:
                self._set_row(lbl_status, lbl_info, "…", ACCENT, "converting")
                success, msg = convert(src, dst)
                if success:
                    ok += 1
                    self._set_row(lbl_status, lbl_info, "OK", SUCCESS, msg)
                else:
                    self._set_row(lbl_status, lbl_info, "ERROR", ERROR, msg)

            self.after(0, lambda v=int(100*(i+1)/total):
                       self.progress.config(value=v))

        self.after(0, self._finish, ok, skipped, total)

    def _set_row(self, lbl_status, lbl_info, status, color, info):
        self.after(0, lambda: (
            lbl_status.config(text=status, fg=color),
            lbl_info.config(text=info, fg=color if color != SUCCESS else MUTED)
        ))

    def _finish(self, ok, skipped, total):
        failed = total - ok - skipped
        parts = [f"{ok} converted"]
        if skipped: parts.append(f"{skipped} skipped")
        if failed:  parts.append(f"{failed} failed")
        color = SUCCESS if failed == 0 else (WARNING if ok > 0 else ERROR)
        self.summary_lbl.config(text="  |  ".join(parts), fg=color)
        self.convert_btn.config(state="normal")


if __name__ == "__main__":
    app = ConverterApp()
    app.mainloop()
