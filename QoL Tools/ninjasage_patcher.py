import tkinter as tk
from tkinter import ttk, filedialog, messagebox
import threading
import shutil
import os
import hashlib
import xml.etree.ElementTree as ET
from datetime import datetime


# ──────────────────────────────────────────────
#  Core patch logic
# ──────────────────────────────────────────────

AIR_DLL_REL   = os.path.join("Adobe AIR", "Versions", "1.0", "Adobe AIR.dll")
APP_XML_REL   = os.path.join("META-INF", "AIR", "application.xml")
SIG_XML_REL   = os.path.join("META-INF", "signatures.xml")
HASH_FILE_REL = os.path.join("META-INF", "AIR", "hash")
NEW_NAMESPACE = "http://ns.adobe.com/air/application/51.1"

PATCHES = [
    (1494881, 0x90),
    (1494882, 0x90),
    (1494898, 0x90),
    (1494899, 0x90),
]


def sha256_file(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.digest()


def run_patch(source_dir, target_dir, log):
    """Full patch process. Calls log(msg, colour) to report progress."""

    def info(msg):  log(msg, "white")
    def ok(msg):    log(msg, "#4ec94e")
    def warn(msg):  log(msg, "#f0c040")
    def err(msg):   log(msg, "#f04040")

    # ── validate source ──────────────────────────────────────────────
    src_air  = os.path.join(source_dir, "Adobe AIR")
    src_meta = os.path.join(source_dir, "META-INF")
    if not os.path.isdir(src_air) or not os.path.isdir(src_meta):
        err("Source folder is missing 'Adobe AIR' or 'META-INF' sub-folders.")
        return False

    # ── 1. Backup ────────────────────────────────────────────────────
    info("[1/5] Backing up existing runtime folders…")
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    for folder in ("Adobe AIR", "META-INF"):
        tgt = os.path.join(target_dir, folder)
        if os.path.isdir(tgt):
            bak = os.path.join(target_dir, f"{folder}_backup_{stamp}")
            try:
                shutil.move(tgt, bak)
                info(f"  Backed up '{folder}' → '{os.path.basename(bak)}'")
            except Exception as e:
                warn(f"  Could not back up '{folder}': {e}")

    # ── 2. Copy runtime ──────────────────────────────────────────────
    info("[2/5] Copying AIR 51.1 runtime from source…")
    for folder in ("Adobe AIR", "META-INF"):
        src = os.path.join(source_dir, folder)
        dst = os.path.join(target_dir, folder)
        try:
            shutil.copytree(src, dst)
            info(f"  Copied '{folder}'")
        except Exception as e:
            err(f"  Failed to copy '{folder}': {e}")
            return False

    # ── 3. Patch DLL ─────────────────────────────────────────────────
    info("[3/5] Patching Adobe AIR.dll…")
    dll_path = os.path.join(target_dir, AIR_DLL_REL)
    if not os.path.isfile(dll_path):
        err(f"  DLL not found at: {dll_path}")
        return False
    try:
        data = bytearray(open(dll_path, "rb").read())
        for offset, value in PATCHES:
            original = data[offset]
            data[offset] = value
            info(f"  Offset {offset} (0x{offset:X}): 0x{original:02X} → 0x{value:02X}")
        open(dll_path, "wb").write(data)
        ok("  DLL patched successfully.")
    except Exception as e:
        err(f"  DLL patch failed: {e}")
        return False

    # ── 4. Update application.xml ────────────────────────────────────
    info("[4/5] Updating application.xml namespace…")
    app_xml_path = os.path.join(target_dir, APP_XML_REL)
    if not os.path.isfile(app_xml_path):
        warn(f"  application.xml not found at {app_xml_path}, skipping.")
    else:
        try:
            tree = ET.parse(app_xml_path)
            root = tree.getroot()

            # Strip old namespace and rewrite with new one
            old_tag = root.tag  # e.g. {http://ns.adobe.com/air/application/2.5}application
            if old_tag.startswith("{"):
                old_ns = old_tag[1:old_tag.index("}")]
            else:
                old_ns = ""

            # Re-register namespace and rewrite all tags
            ET.register_namespace("", NEW_NAMESPACE)
            for elem in tree.iter():
                if elem.tag.startswith("{" + old_ns + "}"):
                    elem.tag = elem.tag.replace("{" + old_ns + "}", "{" + NEW_NAMESPACE + "}")

            tree.write(app_xml_path, xml_declaration=True, encoding="utf-8")
            ok("  application.xml updated.")
        except Exception as e:
            warn(f"  Could not update application.xml: {e}")

    # ── 5. Recalculate hashes ────────────────────────────────────────
    info("[5/5] Recalculating manifest hashes…")
    sig_xml_path  = os.path.join(target_dir, SIG_XML_REL)
    hash_file_path = os.path.join(target_dir, HASH_FILE_REL)

    if not os.path.isfile(sig_xml_path):
        warn(f"  signatures.xml not found, skipping hash step.")
    else:
        try:
            # Parse — preserve namespaces
            ET.register_namespace("", "http://www.w3.org/2000/09/xmldsig#")
            tree = ET.parse(sig_xml_path)
            root = tree.getroot()

            all_hash_bytes = bytearray()

            # Walk every Reference element regardless of namespace depth
            ns = {"ds": "http://www.w3.org/2000/09/xmldsig#"}
            refs = root.findall(".//ds:Reference", ns)
            if not refs:
                # Try without namespace
                refs = root.findall(".//Reference")

            updated = 0
            for ref in refs:
                uri = ref.get("URI", "")
                if not uri:
                    continue
                file_path = os.path.join(target_dir, uri.lstrip("/"))
                if not os.path.isfile(file_path):
                    warn(f"    Skipping missing file: {uri}")
                    continue

                digest_bytes = sha256_file(file_path)
                import base64
                b64 = base64.b64encode(digest_bytes).decode()

                # Find DigestValue child
                dv = ref.find("ds:DigestValue", ns) or ref.find("DigestValue")
                if dv is not None:
                    dv.text = b64
                    updated += 1

                all_hash_bytes.extend(digest_bytes)

            tree.write(sig_xml_path, xml_declaration=True, encoding="utf-8")
            info(f"  Updated {updated} DigestValue entries in signatures.xml")

            # Write binary hash file
            final_hash = hashlib.sha256(bytes(all_hash_bytes)).digest()
            os.makedirs(os.path.dirname(hash_file_path), exist_ok=True)
            open(hash_file_path, "wb").write(final_hash)
            ok("  Binary hash file written.")

        except Exception as e:
            err(f"  Hash recalculation failed: {e}")
            return False

    ok("\n✔  Patch complete! You can now run Ninja Sage.exe")
    return True


# ──────────────────────────────────────────────
#  GUI
# ──────────────────────────────────────────────

class PatcherApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("NinjaSage AIR Patcher")
        self.resizable(False, False)
        self.configure(bg="#1e1e2e")
        self._build_ui()

    def _build_ui(self):
        BG      = "#1e1e2e"
        PANEL   = "#2a2a3e"
        ACCENT  = "#7c6af7"
        FG      = "#cdd6f4"
        ENTRY   = "#313244"
        BTN_FG  = "#ffffff"

        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure("TFrame",       background=BG)
        style.configure("Panel.TFrame", background=PANEL)
        style.configure("TLabel",       background=BG,    foreground=FG,   font=("Segoe UI", 10))
        style.configure("Head.TLabel",  background=BG,    foreground=FG,   font=("Segoe UI", 14, "bold"))
        style.configure("Sub.TLabel",   background=BG,    foreground="#888", font=("Segoe UI", 9))
        style.configure("TEntry",       fieldbackground=ENTRY, foreground=FG,
                        insertcolor=FG, borderwidth=0, font=("Segoe UI", 10))
        style.configure("Accent.TButton", background=ACCENT, foreground=BTN_FG,
                        font=("Segoe UI", 10, "bold"), borderwidth=0, padding=6)
        style.map("Accent.TButton",
                  background=[("active", "#6a5ae0"), ("disabled", "#555")])
        style.configure("Browse.TButton", background=PANEL, foreground=FG,
                        font=("Segoe UI", 9), borderwidth=0, padding=4)
        style.map("Browse.TButton", background=[("active", "#3a3a55")])

        outer = ttk.Frame(self, padding=24)
        outer.grid()

        # Header
        ttk.Label(outer, text="⚔  NinjaSage AIR Patcher", style="Head.TLabel").grid(
            row=0, column=0, columnspan=3, pady=(0, 4), sticky="w")
        ttk.Label(outer, text="Patches Adobe AIR 51.1 runtime into an existing NinjaSage installation.",
                  style="Sub.TLabel").grid(row=1, column=0, columnspan=3, sticky="w", pady=(0, 20))

        # Source dir
        ttk.Label(outer, text="Source folder  (v0.54 with AIR 51.1)").grid(
            row=2, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.src_var = tk.StringVar()
        src_entry = ttk.Entry(outer, textvariable=self.src_var, width=52)
        src_entry.grid(row=3, column=0, columnspan=2, sticky="ew", ipady=4)
        ttk.Button(outer, text="Browse…", style="Browse.TButton",
                   command=self._browse_src).grid(row=3, column=2, padx=(8, 0))

        ttk.Label(outer, text="Must contain 'Adobe AIR' and 'META-INF' sub-folders.",
                  style="Sub.TLabel").grid(row=4, column=0, columnspan=3, sticky="w", pady=(2, 14))

        # Target dir
        ttk.Label(outer, text="Target folder  (NinjaSage installation)").grid(
            row=5, column=0, columnspan=3, sticky="w", pady=(0, 2))
        self.tgt_var = tk.StringVar()
        tgt_entry = ttk.Entry(outer, textvariable=self.tgt_var, width=52)
        tgt_entry.grid(row=6, column=0, columnspan=2, sticky="ew", ipady=4)
        ttk.Button(outer, text="Browse…", style="Browse.TButton",
                   command=self._browse_tgt).grid(row=6, column=2, padx=(8, 0))

        ttk.Label(outer, text="The folder that contains NinjaSage.exe.",
                  style="Sub.TLabel").grid(row=7, column=0, columnspan=3, sticky="w", pady=(2, 20))

        # Separator
        sep = tk.Frame(outer, bg="#3a3a55", height=1)
        sep.grid(row=8, column=0, columnspan=3, sticky="ew", pady=(0, 16))

        # Log console
        self.log_box = tk.Text(outer, width=68, height=16, bg="#12121e", fg="white",
                               font=("Consolas", 9), state="disabled", relief="flat",
                               insertbackground="white", bd=0)
        self.log_box.grid(row=9, column=0, columnspan=3)
        self.log_box.tag_config("white",   foreground="white")
        self.log_box.tag_config("#4ec94e", foreground="#4ec94e")
        self.log_box.tag_config("#f0c040", foreground="#f0c040")
        self.log_box.tag_config("#f04040", foreground="#f04040")

        sb = ttk.Scrollbar(outer, command=self.log_box.yview)
        sb.grid(row=9, column=3, sticky="ns")
        self.log_box.configure(yscrollcommand=sb.set)

        # Run button
        self.run_btn = ttk.Button(outer, text="▶  Run Full Patch", style="Accent.TButton",
                                  command=self._start_patch)
        self.run_btn.grid(row=10, column=0, columnspan=3, pady=(16, 0), ipadx=16, ipady=4)

    # ── helpers ──────────────────────────────────────────────────────

    def _browse_src(self):
        d = filedialog.askdirectory(title="Select source folder (v0.54)")
        if d:
            self.src_var.set(d)

    def _browse_tgt(self):
        d = filedialog.askdirectory(title="Select NinjaSage installation folder")
        if d:
            self.tgt_var.set(d)

    def _log(self, msg, colour="white"):
        self.log_box.configure(state="normal")
        self.log_box.insert("end", msg + "\n", colour)
        self.log_box.see("end")
        self.log_box.configure(state="disabled")

    def _start_patch(self):
        src = self.src_var.get().strip()
        tgt = self.tgt_var.get().strip()

        if not src or not tgt:
            messagebox.showwarning("Missing paths", "Please select both source and target folders.")
            return
        if not os.path.isdir(src):
            messagebox.showerror("Invalid source", f"Source folder not found:\n{src}")
            return
        if not os.path.isdir(tgt):
            messagebox.showerror("Invalid target", f"Target folder not found:\n{tgt}")
            return

        self.run_btn.configure(state="disabled")
        self.log_box.configure(state="normal")
        self.log_box.delete("1.0", "end")
        self.log_box.configure(state="disabled")
        self._log("─── Starting Patch Process ───", "#7c6af7")

        def worker():
            try:
                run_patch(src, tgt, self._log)
            finally:
                self.after(0, lambda: self.run_btn.configure(state="normal"))

        threading.Thread(target=worker, daemon=True).start()


if __name__ == "__main__":
    app = PatcherApp()
    app.mainloop()
