"""
Gestor de Altas y Bajas de Usuarios - Windows Server
Interfaz gráfica para administración de usuarios mediante scripts ASO
"""

import tkinter as tk
from tkinter import ttk, messagebox
import subprocess
import os
import sys
import threading
import datetime


# ─── Configuración de Scripts ASO ──────────────────────────────────────────
# Modifica estas rutas para apuntar a tus scripts reales
SCRIPTS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "scripts")

SCRIPT_CREAR    = os.path.join(SCRIPTS_DIR, "crear_usuario.ps1")   # $1=usuario, $2=contraseña
SCRIPT_ELIMINAR = os.path.join(SCRIPTS_DIR, "eliminar_usuario.ps1") # $1=usuario
SCRIPT_LISTAR   = os.path.join(SCRIPTS_DIR, "listar_usuarios.ps1")

# PowerShell por defecto en Windows Server
POWERSHELL = r"C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe"


# ─── Colores y fuentes ──────────────────────────────────────────────────────
DARK_BG     = "#0f1117"
PANEL_BG    = "#1a1d27"
CARD_BG     = "#22263a"
ACCENT      = "#4f8ef7"
ACCENT2     = "#e05c7a"
SUCCESS     = "#3ecf8e"
WARNING     = "#f5a623"
TEXT_MAIN   = "#e8eaf6"
TEXT_DIM    = "#7c84a3"
BORDER      = "#2e3250"

FONT_TITLE  = ("Consolas", 22, "bold")
FONT_HEAD   = ("Consolas", 11, "bold")
FONT_BODY   = ("Consolas", 10)
FONT_SMALL  = ("Consolas", 9)
FONT_LOG    = ("Consolas", 9)


class GestorUsuarios(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Gestor de Usuarios — Windows Server")
        self.geometry("900x650")
        self.minsize(800, 580)
        self.configure(bg=DARK_BG)
        self.resizable(True, True)

        # Icono (ignorar si no existe)
        try:
            self.iconbitmap("icono.ico")
        except Exception:
            pass

        self._build_ui()
        self._log("Sistema iniciado. Listo para operar.", "INFO")

    # ─── CONSTRUCCIÓN DE UI ────────────────────────────────────────────────

    def _build_ui(self):
        # Barra superior
        header = tk.Frame(self, bg=PANEL_BG, height=64)
        header.pack(fill="x", side="top")
        header.pack_propagate(False)

        tk.Label(header, text="⬡", font=("Consolas", 24), bg=PANEL_BG,
                 fg=ACCENT).pack(side="left", padx=(20, 6), pady=10)
        tk.Label(header, text="GESTOR DE USUARIOS", font=FONT_TITLE,
                 bg=PANEL_BG, fg=TEXT_MAIN).pack(side="left", pady=10)

        self.lbl_hora = tk.Label(header, text="", font=FONT_SMALL,
                                  bg=PANEL_BG, fg=TEXT_DIM)
        self.lbl_hora.pack(side="right", padx=20)
        self._tick()

        # Separador
        tk.Frame(self, bg=ACCENT, height=2).pack(fill="x")

        # Contenedor principal
        main = tk.Frame(self, bg=DARK_BG)
        main.pack(fill="both", expand=True, padx=20, pady=16)

        # Columna izquierda: formularios
        left = tk.Frame(main, bg=DARK_BG)
        left.pack(side="left", fill="both", expand=True, padx=(0, 10))

        self._build_form_alta(left)
        self._build_form_baja(left)

        # Columna derecha: log
        right = tk.Frame(main, bg=DARK_BG)
        right.pack(side="right", fill="both", expand=True)

        self._build_log(right)

        # Barra de estado
        self._build_statusbar()

    def _build_form_alta(self, parent):
        card = self._card(parent, "➕  ALTA DE USUARIO")

        # Usuario
        self._label(card, "Nombre de usuario *")
        self.entry_usuario_alta = self._entry(card)

        # Contraseña
        self._label(card, "Contraseña *")
        self.entry_pass = self._entry(card, show="●")

        # Confirmar contraseña
        self._label(card, "Confirmar contraseña *")
        self.entry_pass2 = self._entry(card, show="●")

        # Grupo (opcional)
        self._label(card, "Grupo  (opcional)")
        self.entry_grupo = self._entry(card, placeholder="p.ej. Administradores")

        # Botón
        btn = tk.Button(
            card, text="  CREAR USUARIO  EN EL SISTEMA",
            font=FONT_HEAD, bg=ACCENT, fg="white",
            activebackground="#3a7be0", activeforeground="white",
            relief="flat", cursor="hand2", pady=8,
            command=self._accion_alta
        )
        btn.pack(fill="x", pady=(12, 4))
        card.pack(fill="x", pady=(0, 12))

    def _build_form_baja(self, parent):
        card = self._card(parent, "➖  BAJA DE USUARIO")

        self._label(card, "Nombre de usuario *")
        self.entry_usuario_baja = self._entry(card)

        # Checkbox confirmación
        self.var_confirm = tk.BooleanVar()
        chk = tk.Checkbutton(
            card, text="Confirmo que deseo eliminar este usuario",
            variable=self.var_confirm,
            bg=CARD_BG, fg=TEXT_DIM, selectcolor=CARD_BG,
            activebackground=CARD_BG, activeforeground=TEXT_MAIN,
            font=FONT_SMALL, cursor="hand2"
        )
        chk.pack(anchor="w", pady=(6, 0))

        btn = tk.Button(
            card, text="  ELIMINAR USUARIO  DEL SISTEMA",
            font=FONT_HEAD, bg=ACCENT2, fg="white",
            activebackground="#c0435f", activeforeground="white",
            relief="flat", cursor="hand2", pady=8,
            command=self._accion_baja
        )
        btn.pack(fill="x", pady=(10, 4))
        card.pack(fill="x")

    def _build_log(self, parent):
        tk.Label(parent, text="REGISTRO DE OPERACIONES",
                 font=FONT_HEAD, bg=DARK_BG, fg=TEXT_DIM).pack(anchor="w", pady=(0, 6))

        frame_log = tk.Frame(parent, bg=CARD_BG, relief="flat",
                              highlightbackground=BORDER, highlightthickness=1)
        frame_log.pack(fill="both", expand=True)

        self.txt_log = tk.Text(
            frame_log, bg=CARD_BG, fg=TEXT_MAIN,
            font=FONT_LOG, relief="flat", wrap="word",
            state="disabled", padx=10, pady=10,
            insertbackground=ACCENT, selectbackground=ACCENT
        )

        scroll = ttk.Scrollbar(frame_log, command=self.txt_log.yview)
        self.txt_log.configure(yscrollcommand=scroll.set)

        scroll.pack(side="right", fill="y")
        self.txt_log.pack(fill="both", expand=True)

        # Tags de color para el log
        self.txt_log.tag_config("INFO",    foreground=TEXT_DIM)
        self.txt_log.tag_config("OK",      foreground=SUCCESS)
        self.txt_log.tag_config("ERROR",   foreground=ACCENT2)
        self.txt_log.tag_config("WARN",    foreground=WARNING)
        self.txt_log.tag_config("CMD",     foreground=ACCENT)
        self.txt_log.tag_config("OUTPUT",  foreground="#aab4d4")
        self.txt_log.tag_config("TIME",    foreground="#4a4f6a")

        # Botón limpiar log
        tk.Button(
            parent, text="Limpiar registro",
            font=FONT_SMALL, bg=PANEL_BG, fg=TEXT_DIM,
            activebackground=CARD_BG, activeforeground=TEXT_MAIN,
            relief="flat", cursor="hand2",
            command=self._limpiar_log
        ).pack(anchor="e", pady=(6, 0))

    def _build_statusbar(self):
        bar = tk.Frame(self, bg=PANEL_BG, height=28)
        bar.pack(fill="x", side="bottom")
        bar.pack_propagate(False)

        self.lbl_status = tk.Label(
            bar, text="● Listo",
            font=FONT_SMALL, bg=PANEL_BG, fg=SUCCESS
        )
        self.lbl_status.pack(side="left", padx=16, pady=4)

        tk.Label(bar, text=f"Scripts: {SCRIPTS_DIR}",
                 font=FONT_SMALL, bg=PANEL_BG, fg=TEXT_DIM
                 ).pack(side="right", padx=16, pady=4)

    # ─── ACCIONES ──────────────────────────────────────────────────────────

    def _accion_alta(self):
        usuario  = self.entry_usuario_alta.get().strip()
        password = self.entry_pass.get()
        password2 = self.entry_pass2.get()
        grupo    = self.entry_grupo.get().strip()

        # Validaciones
        if not usuario:
            self._log("Campo 'Nombre de usuario' vacío.", "ERROR")
            messagebox.showerror("Error", "El nombre de usuario es obligatorio.")
            return
        if not password:
            self._log("Campo 'Contraseña' vacío.", "ERROR")
            messagebox.showerror("Error", "La contraseña es obligatoria.")
            return
        if password != password2:
            self._log("Las contraseñas no coinciden.", "ERROR")
            messagebox.showerror("Error", "Las contraseñas no coinciden.")
            return

        msg = f"¿Crear usuario «{usuario}»" + (f" en grupo «{grupo}»?" if grupo else "?")
        if not messagebox.askyesno("Confirmar alta", msg):
            return

        args = [usuario, password]
        if grupo:
            args.append(grupo)

        self._ejecutar_script(SCRIPT_CREAR, args, f"ALTA de usuario «{usuario}»")

    def _accion_baja(self):
        usuario = self.entry_usuario_baja.get().strip()

        if not usuario:
            self._log("Campo 'Nombre de usuario' vacío.", "ERROR")
            messagebox.showerror("Error", "El nombre de usuario es obligatorio.")
            return
        if not self.var_confirm.get():
            self._log("No se marcó la casilla de confirmación.", "WARN")
            messagebox.showwarning("Atención", "Marca la casilla de confirmación para continuar.")
            return

        if not messagebox.askyesno("Confirmar baja",
                                   f"¿Eliminar definitivamente el usuario «{usuario}»?\n\nEsta acción no se puede deshacer."):
            return

        self._ejecutar_script(SCRIPT_ELIMINAR, [usuario], f"BAJA de usuario «{usuario}»")

    # ─── EJECUCIÓN DE SCRIPT (hilo secundario) ─────────────────────────────

    def _ejecutar_script(self, script_path, args, descripcion):
        """Lanza el script PowerShell en un hilo para no bloquear la UI."""
        self._set_status("Ejecutando…", WARNING)
        self._log(f"Iniciando: {descripcion}", "INFO")

        def run():
            try:
                if not os.path.isfile(script_path):
                    self.after(0, lambda: self._log(
                        f"Script no encontrado: {script_path}\n"
                        "→ Coloca tus scripts .ps1 en la carpeta /scripts/", "ERROR"))
                    self.after(0, lambda: self._set_status("Error: script no encontrado", ACCENT2))
                    return

                cmd = [POWERSHELL, "-ExecutionPolicy", "Bypass",
                       "-File", script_path] + args

                self.after(0, lambda: self._log("CMD: " + " ".join(cmd), "CMD"))

                result = subprocess.run(
                    cmd,
                    capture_output=True,
                    text=True,
                    timeout=30
                )

                if result.stdout:
                    for line in result.stdout.strip().splitlines():
                        self.after(0, lambda l=line: self._log(l, "OUTPUT"))

                if result.returncode == 0:
                    self.after(0, lambda: self._log(f"✔ {descripcion} completada correctamente.", "OK"))
                    self.after(0, lambda: self._set_status("Operación completada", SUCCESS))
                    self.after(0, self._limpiar_campos)
                else:
                    err = result.stderr.strip() or f"Código de salida: {result.returncode}"
                    self.after(0, lambda e=err: self._log(f"✘ Error: {e}", "ERROR"))
                    self.after(0, lambda: self._set_status("Error en la operación", ACCENT2))

            except subprocess.TimeoutExpired:
                self.after(0, lambda: self._log("✘ Tiempo de espera agotado (30s).", "ERROR"))
                self.after(0, lambda: self._set_status("Timeout", ACCENT2))
            except Exception as exc:
                self.after(0, lambda e=exc: self._log(f"✘ Excepción inesperada: {e}", "ERROR"))
                self.after(0, lambda: self._set_status("Error", ACCENT2))

        threading.Thread(target=run, daemon=True).start()

    # ─── HELPERS UI ────────────────────────────────────────────────────────

    def _card(self, parent, titulo):
        outer = tk.Frame(parent, bg=CARD_BG,
                         highlightbackground=BORDER, highlightthickness=1)
        tk.Label(outer, text=titulo, font=FONT_HEAD,
                 bg=CARD_BG, fg=ACCENT).pack(anchor="w", padx=14, pady=(12, 6))
        tk.Frame(outer, bg=BORDER, height=1).pack(fill="x", padx=14)
        inner = tk.Frame(outer, bg=CARD_BG)
        inner.pack(fill="x", padx=14, pady=(8, 14))
        outer._inner = inner
        return inner

    def _label(self, parent, texto):
        tk.Label(parent, text=texto, font=FONT_SMALL,
                 bg=CARD_BG, fg=TEXT_DIM).pack(anchor="w", pady=(6, 1))

    def _entry(self, parent, show=None, placeholder=""):
        frame = tk.Frame(parent, bg=BORDER)
        frame.pack(fill="x", ipady=1)
        entry = tk.Entry(
            frame, font=FONT_BODY, bg="#1e2235", fg=TEXT_MAIN,
            insertbackground=ACCENT, relief="flat", bd=0,
            show=show or ""
        )
        entry.pack(fill="x", padx=1, pady=1, ipady=5)
        if placeholder:
            entry.insert(0, placeholder)
            entry.config(fg=TEXT_DIM)
            entry.bind("<FocusIn>",  lambda e: self._ph_clear(entry, placeholder))
            entry.bind("<FocusOut>", lambda e: self._ph_restore(entry, placeholder))
        return entry

    def _ph_clear(self, entry, ph):
        if entry.get() == ph:
            entry.delete(0, "end")
            entry.config(fg=TEXT_MAIN)

    def _ph_restore(self, entry, ph):
        if not entry.get():
            entry.insert(0, ph)
            entry.config(fg=TEXT_DIM)

    def _log(self, mensaje, nivel="INFO"):
        now = datetime.datetime.now().strftime("%H:%M:%S")
        self.txt_log.config(state="normal")
        self.txt_log.insert("end", f"[{now}] ", "TIME")
        self.txt_log.insert("end", f"{mensaje}\n", nivel)
        self.txt_log.see("end")
        self.txt_log.config(state="disabled")

    def _limpiar_log(self):
        self.txt_log.config(state="normal")
        self.txt_log.delete("1.0", "end")
        self.txt_log.config(state="disabled")
        self._log("Registro limpiado.", "INFO")

    def _limpiar_campos(self):
        for entry in (self.entry_usuario_alta, self.entry_pass,
                      self.entry_pass2, self.entry_usuario_baja):
            entry.delete(0, "end")
        self.entry_grupo.delete(0, "end")
        self.var_confirm.set(False)

    def _set_status(self, texto, color=SUCCESS):
        dot = "●"
        self.lbl_status.config(text=f"{dot} {texto}", fg=color)

    def _tick(self):
        now = datetime.datetime.now().strftime("%d/%m/%Y  %H:%M:%S")
        self.lbl_hora.config(text=now)
        self.after(1000, self._tick)


# ─── ENTRADA ───────────────────────────────────────────────────────────────

if __name__ == "__main__":
    app = GestorUsuarios()
    app.mainloop()
