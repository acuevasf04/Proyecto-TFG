# listar_usuarios.ps1
# Lista todos los usuarios locales del sistema
Get-LocalUser | Select-Object Name, Enabled, LastLogon | Format-Table -AutoSize
exit 0
