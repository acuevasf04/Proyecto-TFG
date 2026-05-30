# eliminar_usuario.ps1
# Argumento: $1=usuario
param(
    [Parameter(Mandatory=$true)] [string]$Usuario
)

try {
    Remove-LocalUser -Name $Usuario -ErrorAction Stop
    Write-Output "Usuario '$Usuario' eliminado correctamente."
    exit 0
} catch {
    Write-Error "Error al eliminar usuario: $_"
    exit 1
}
