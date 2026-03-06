"""
NinjaSage CustomClientBuilder
──────────────────────────
Creates a custom-named, custom-icon copy of the NinjaSage game package.

PyInstaller usage:
  pyinstaller --onefile --windowed --icon=your_icon.ico \
              --add-binary "rcedit.exe;." \
              CustomClientBuilder.py

  If rcedit.exe is NOT bundled, the tool will auto-download it from GitHub
  on first use and cache it next to the compiled executable.
"""

import os
import re
import sys
import subprocess
import threading
import shutil
import urllib.request
import xml.etree.ElementTree as ET
import tkinter as tk
from tkinter import ttk, filedialog, messagebox


# ── Constants ─────────────────────────────────────────────────────────────────

SOURCE_EXE_NAME = "Ninja Sage.exe"
APP_XML_REL     = os.path.join("META-INF", "AIR", "application.xml")
RCEDIT_URL      = "https://github.com/electron/rcedit/releases/latest/download/rcedit-x64.exe"

# ── PyInstaller helpers ────────────────────────────────────────────────────────

def _tool_dir() -> str:
    """Directory that contains this script or compiled executable."""
    if getattr(sys, "frozen", False):
        return os.path.dirname(sys.executable)
    return os.path.dirname(os.path.abspath(__file__))


def _bundled_path(relative: str) -> str:
    """Return path to a file bundled via PyInstaller --add-data / --add-binary."""
    base = getattr(sys, "_MEIPASS", _tool_dir())
    return os.path.join(base, relative)


def _find_rcedit() -> str | None:
    """Locate rcedit.exe: bundled → tool folder → None."""
    bundled = _bundled_path("rcedit.exe")
    if os.path.isfile(bundled):
        return bundled
    local = os.path.join(_tool_dir(), "rcedit.exe")
    if os.path.isfile(local):
        return local
    return None


def _download_rcedit(log_fn) -> str | None:
    """Download rcedit.exe from GitHub and save it next to this tool."""
    dest = os.path.join(_tool_dir(), "rcedit.exe")
    log_fn(f"  Downloading rcedit.exe from GitHub…", "white")
    try:
        urllib.request.urlretrieve(RCEDIT_URL, dest)
        log_fn(f"  Saved to: {dest}", "#4ec94e")
        return dest
    except Exception as exc:
        log_fn(f"  Download failed: {exc}", "#f04040")
        return None


# ── Filename sanitisation ──────────────────────────────────────────────────────

def _safe_name(raw: str) -> str:
    """Strip characters illegal in Windows filenames and trim whitespace."""
    return re.sub(r'[\\/:*?"<>|]', "", raw).strip()


# ── Core build logic ───────────────────────────────────────────────────────────

def run_build(source_dir: str, output_parent: str, app_name: str,
              icon_path: str, log_fn) -> bool:
    """
    1. Copy source_dir → output_parent/<app_name>/
    2. Rename 'Ninja Sage.exe' → '<app_name>.exe'
    3. Patch META-INF/AIR/application.xml  (<filename> and <name>)
    4. Apply custom icon via rcedit (downloaded on demand if missing)
    """

    def info(m): log_fn(m, "white")
    def ok(m):   log_fn(m, "#4ec94e")
    def warn(m): log_fn(m, "#f0c040")
    def err(m):  log_fn(m, "#f04040")

    # ── validate inputs ───────────────────────────────────────────────────────
    if not os.path.isdir(source_dir):
        err("Source folder not found.")
        return False

    src_exe = os.path.join(source_dir, SOURCE_EXE_NAME)
    if not os.path.isfile(src_exe):
        err(f"Source folder is missing '{SOURCE_EXE_NAME}'.\n"
            f"Make sure you're pointing at the NinjaSage Client folder.")
        return False

    safe = _safe_name(app_name)
    if not safe:
        err("App name is empty or contains only invalid characters.")
        return False

    output_dir = os.path.join(output_parent, safe)
    if os.path.exists(output_dir):
        err(f"Output folder already exists:\n  {output_dir}\n"
            f"Delete it first or choose a different name / output location.")
        return False

    # ── 1. Copy ───────────────────────────────────────────────────────────────
    info(f"[1/4] Copying source to: {output_dir}")
    try:
        shutil.copytree(source_dir, output_dir)
        ok(f"  Copied {sum(len(f) for _, _, f in os.walk(output_dir))} files.")
    except Exception as exc:
        err(f"  Copy failed: {exc}")
        return False

    # ── 2. Rename EXE ─────────────────────────────────────────────────────────
    info(f"[2/4] Renaming '{SOURCE_EXE_NAME}' → '{safe}.exe'")
    old_exe = os.path.join(output_dir, SOURCE_EXE_NAME)
    new_exe = os.path.join(output_dir, f"{safe}.exe")
    try:
        os.rename(old_exe, new_exe)
        ok(f"  Done.")
    except Exception as exc:
        err(f"  Rename failed: {exc}")
        return False

    # ── 3. Patch application.xml ──────────────────────────────────────────────
    info("[3/4] Patching application.xml…")
    app_xml = os.path.join(output_dir, APP_XML_REL)
    if not os.path.isfile(app_xml):
        warn("  application.xml not found — skipping.")
    else:
        try:
            tree = ET.parse(app_xml)
            root = tree.getroot()

            # Detect AIR namespace (varies by AIR version)
            ns_prefix = ""
            if root.tag.startswith("{"):
                ns_uri    = root.tag[1 : root.tag.index("}")]
                ns_prefix = f"{{{ns_uri}}}"
                ET.register_namespace("", ns_uri)

            changed = 0
            for tag in ("filename", "name"):
                elem = root.find(f"{ns_prefix}{tag}")
                if elem is not None:
                    old_val   = elem.text or ""
                    elem.text = safe
                    info(f"  <{tag}> '{old_val}' → '{safe}'")
                    changed += 1

            tree.write(app_xml, xml_declaration=True, encoding="utf-8")
            ok(f"  Updated {changed} element(s).")
        except Exception as exc:
            warn(f"  Could not patch application.xml: {exc}")

    # ── 4. Apply icon ─────────────────────────────────────────────────────────
    if icon_path and os.path.isfile(icon_path):
        info("[4/4] Applying custom icon…")
        rcedit = _find_rcedit()
        if not rcedit:
            warn("  rcedit.exe not found — attempting download…")
            rcedit = _download_rcedit(log_fn)

        if rcedit:
            try:
                result = subprocess.run(
                    [rcedit, new_exe, "--set-icon", icon_path],
                    capture_output=True, text=True, timeout=30
                )
                if result.returncode == 0:
                    ok("  Icon applied.")
                else:
                    warn(f"  rcedit exited {result.returncode}: "
                         f"{(result.stderr or result.stdout).strip()}")
            except subprocess.TimeoutExpired:
                warn("  rcedit timed out.")
            except Exception as exc:
                warn(f"  Icon change failed: {exc}")
        else:
            warn("  Skipping icon — rcedit unavailable.\n"
                 "  Place rcedit.exe next to this tool and re-run to apply icons.")
    else:
        info("[4/4] No icon selected — keeping original icon.")

    ok(f"\n✔  Build complete!\n"
       f"   Folder : {output_dir}\n"
       f"   Run    : {safe}.exe")
    return True


# ── GUI ────────────────────────────────────────────────────────────────────────

BG     = "#1e1e2e"
PANEL  = "#2a2a3e"
ACCENT = "#7c6af7"
FG     = "#cdd6f4"
ENTRY  = "#313244"
SUB    = "#888899"
GREEN  = "#4ec94e"
YELLOW = "#f0c040"
RED    = "#f04040"


class BranderApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Ninja Saga Custom Launcher Builder")
        self.resizable(False, False)
        self.configure(bg=BG)
        self._build_ui()
        self._try_autodetect_source()

    # ── UI construction ───────────────────────────────────────────────────────

    def _build_ui(self):
        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure("TFrame",        background=BG)
        style.configure("TLabel",        background=BG,    foreground=FG,  font=("Segoe UI", 10))
        style.configure("Head.TLabel",   background=BG,    foreground=FG,  font=("Segoe UI", 14, "bold"))
        style.configure("Sub.TLabel",    background=BG,    foreground=SUB, font=("Segoe UI", 9))
        style.configure("TEntry",        fieldbackground=ENTRY, foreground=FG,
                        insertcolor=FG, borderwidth=0, font=("Segoe UI", 10))
        style.configure("Accent.TButton", background=ACCENT, foreground="#fff",
                        font=("Segoe UI", 10, "bold"), borderwidth=0, padding=6)
        style.map("Accent.TButton",
                  background=[("active", "#6a5ae0"), ("disabled", "#444")])
        style.configure("Browse.TButton", background=PANEL, foreground=FG,
                        font=("Segoe UI", 9), borderwidth=0, padding=4)
        style.map("Browse.TButton", background=[("active", "#3a3a55")])
        style.configure("TSeparator", background="#3a3a55")

        outer = ttk.Frame(self, padding=24)
        outer.grid()

        # ── header ────────────────────────────────────────────────────────────
        ttk.Label(outer, text="Ninja Saga Custom Launcher Builder",
                  style="Head.TLabel").grid(row=0, column=0, columnspan=3,
                                            pady=(0, 2), sticky="w")
        ttk.Label(outer,
                  text="Creates a custom-named copy of the game with your own icon.",
                  style="Sub.TLabel").grid(row=1, column=0, columnspan=3,
                                           sticky="w", pady=(0, 18))

        # ── source folder ─────────────────────────────────────────────────────
        ttk.Label(outer, text="NinjaSage Client folder").grid(
            row=2, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.src_var = tk.StringVar()
        ttk.Entry(outer, textvariable=self.src_var, width=54).grid(
            row=3, column=0, columnspan=2, sticky="ew", ipady=4)
        ttk.Button(outer, text="Browse…", style="Browse.TButton",
                   command=self._browse_src).grid(row=3, column=2, padx=(8, 0))
        ttk.Label(outer, text="The folder containing 'Ninja Sage.exe'.",
                  style="Sub.TLabel").grid(row=4, column=0, columnspan=3,
                                           sticky="w", pady=(2, 14))

        # ── output folder ─────────────────────────────────────────────────────
        ttk.Label(outer, text="Output location").grid(
            row=5, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.out_var = tk.StringVar()
        ttk.Entry(outer, textvariable=self.out_var, width=54).grid(
            row=6, column=0, columnspan=2, sticky="ew", ipady=4)
        ttk.Button(outer, text="Browse…", style="Browse.TButton",
                   command=self._browse_out).grid(row=6, column=2, padx=(8, 0))
        ttk.Label(outer, text="A sub-folder named after your app will be created here.",
                  style="Sub.TLabel").grid(row=7, column=0, columnspan=3,
                                           sticky="w", pady=(2, 14))

        # ── app name ──────────────────────────────────────────────────────────
        ttk.Label(outer, text="App name  (becomes the .exe filename)").grid(
            row=8, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.name_var = tk.StringVar(value="Ninja Sage")
        ttk.Entry(outer, textvariable=self.name_var, width=40).grid(
            row=9, column=0, columnspan=2, sticky="w", ipady=4)
        ttk.Label(outer, text="e.g.  Shadow Realm  →  Shadow Realm.exe",
                  style="Sub.TLabel").grid(row=10, column=0, columnspan=3,
                                           sticky="w", pady=(2, 14))

        # ── icon file ─────────────────────────────────────────────────────────
        ttk.Label(outer, text="Custom icon  (.ico file — optional)").grid(
            row=11, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.ico_var = tk.StringVar()
        ttk.Entry(outer, textvariable=self.ico_var, width=54).grid(
            row=12, column=0, columnspan=2, sticky="ew", ipady=4)
        ttk.Button(outer, text="Browse…", style="Browse.TButton",
                   command=self._browse_ico).grid(row=12, column=2, padx=(8, 0))
        ttk.Label(outer,
                  text="Leave blank to keep the original NinjaSage icon.  "
                       "Requires rcedit.exe (auto-downloaded on first use).",
                  style="Sub.TLabel").grid(row=13, column=0, columnspan=3,
                                           sticky="w", pady=(2, 16))

        # ── separator ─────────────────────────────────────────────────────────
        tk.Frame(outer, bg="#3a3a55", height=1).grid(
            row=14, column=0, columnspan=3, sticky="ew", pady=(0, 14))

        # ── log ───────────────────────────────────────────────────────────────
        self.log_box = tk.Text(outer, width=68, height=14, bg="#12121e", fg=FG,
                               font=("Consolas", 9), state="disabled",
                               relief="flat", bd=0, insertbackground=FG)
        self.log_box.grid(row=15, column=0, columnspan=3)
        for tag, colour in [("white", FG), ("ok", GREEN),
                             ("warn", YELLOW), ("err", RED),
                             ("accent", ACCENT)]:
            self.log_box.tag_config(tag, foreground=colour)

        sb = ttk.Scrollbar(outer, command=self.log_box.yview)
        sb.grid(row=15, column=3, sticky="ns")
        self.log_box.configure(yscrollcommand=sb.set)

        # ── build button ──────────────────────────────────────────────────────
        self.build_btn = ttk.Button(outer, text="▶  Build Launcher",
                                    style="Accent.TButton",
                                    command=self._start_build)
        self.build_btn.grid(row=16, column=0, columnspan=3,
                            pady=(14, 0), ipadx=24, ipady=4)

    # ── auto-detect source ────────────────────────────────────────────────────

    def _try_autodetect_source(self):
        """Pre-fill source if a 'Client' folder with the game EXE is nearby."""
        candidates = [
            os.path.join(_tool_dir(), "..", "Client"),
            os.path.join(_tool_dir(), "Client"),
        ]
        for path in candidates:
            norm = os.path.normpath(path)
            if os.path.isfile(os.path.join(norm, SOURCE_EXE_NAME)):
                self.src_var.set(norm)
                # Default output to parent of Client
                self.out_var.set(os.path.dirname(norm))
                self._log("Auto-detected Client folder.", "accent")
                break

    # ── browse helpers ────────────────────────────────────────────────────────

    def _browse_src(self):
        d = filedialog.askdirectory(title="Select NinjaSage Client folder")
        if d:
            self.src_var.set(d)

    def _browse_out(self):
        d = filedialog.askdirectory(title="Select output location")
        if d:
            self.out_var.set(d)

    def _browse_ico(self):
        f = filedialog.askopenfilename(
            title="Select icon file",
            filetypes=[("Icon files", "*.ico"), ("All files", "*.*")])
        if f:
            self.ico_var.set(f)

    # ── logging ───────────────────────────────────────────────────────────────

    def _log(self, msg: str, colour: str = "white"):
        # Map colour hex to tag names
        tag_map = {
            "white":   "white",
            FG:        "white",
            "#4ec94e": "ok",
            "#f0c040": "warn",
            "#f04040": "err",
            ACCENT:    "accent",
        }
        tag = tag_map.get(colour, "white")
        self.log_box.configure(state="normal")
        self.log_box.insert("end", msg + "\n", tag)
        self.log_box.see("end")
        self.log_box.configure(state="disabled")

    # ── build ─────────────────────────────────────────────────────────────────

    def _start_build(self):
        src  = self.src_var.get().strip()
        out  = self.out_var.get().strip()
        name = self.name_var.get().strip()
        ico  = self.ico_var.get().strip()

        if not src:
            messagebox.showwarning("Missing field", "Please select the NinjaSage Client folder.")
            return
        if not out:
            messagebox.showwarning("Missing field", "Please select an output location.")
            return
        if not name:
            messagebox.showwarning("Missing field", "Please enter an app name.")
            return

        self.build_btn.configure(state="disabled")
        self.log_box.configure(state="normal")
        self.log_box.delete("1.0", "end")
        self.log_box.configure(state="disabled")
        self._log("─── Starting Build ───", "accent")

        def worker():
            try:
                run_build(src, out, name, ico or None, self._log)
            finally:
                self.after(0, lambda: self.build_btn.configure(state="normal"))

        threading.Thread(target=worker, daemon=True).start()


# ── entry point ────────────────────────────────────────────────────────────────

if __name__ == "__main__":
    app = BranderApp()
    app.mainloop()
