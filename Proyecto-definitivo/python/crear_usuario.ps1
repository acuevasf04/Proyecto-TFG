# crear_usuario.ps1
# Argumentos: $1=usuario  $2=contraseña  $3=grupo (opcional)
param(
    [Parameter(Mandatory=$true)]  [string]$Usuario,
    [Parameter(Mandatory=$true)]  [string]$Password,
    [Parameter(Mandatory=$false)] [string]$Grupo = ""
)

$SecurePass = ConvertTo-SecureString $Password -AsPlainText -Force

try {
    New-LocalUser -Name $Usuario -Password $SecurePass -FullName $Usuario `
                  -Description "Creado via Gestor ASO" -ErrorAction Stop
    Write-Output "Usuario '$Usuario' creado correctamente."

    if ($Grupo -ne "") {
        Add-LocalGroupMember -Group $Grupo -Member $Usuario -ErrorAction Stop
        Write-Output "Usuario '$Usuario' añadido al grupo '$Grupo'."
    }
    exit 0
} catch {
    Write-Error "Error al crear usuario: $_"
    exit 1
}
