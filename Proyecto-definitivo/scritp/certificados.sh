#!/bin/bash

if [ $# -eq 0 ]; then
    TARGETS=("/etc/" "/home/" "/root/")
else
    TARGETS=("$@")
fi

EMAIL_TO="acuevasf04@iesalbarregas.es"
EMAIL_FROM="no-reply@aromaris.com"
SUBJECT="🚨 Alerta de Certificados: $(hostname)"
# ==============================================================

echo "======================================================================="
echo " Buscando certificados en: ${TARGETS[*]}"
echo "======================================================================="
printf "%-55s | %-10s | %s\n" "Ruta del Certificado" "Días" "Estado"
echo "-----------------------------------------------------------------------"

NOW=$(date +%s)
ALERT_REPORT=""

while read -r cert; do

    REAL_PATH=$(readlink -f "$cert" 2>/dev/null)
    if [[ "$REAL_PATH" == *"/usr/share/ca-certificates/"* ]]; then continue; fi
    if [[ "$cert" == *"/ca-certificates.crt" ]] || [[ "$cert" == *"dhparam"* ]]; then continue; fi

    EXP_DATE_STR=$(openssl x509 -enddate -noout -in "$cert" 2>/dev/null | cut -d= -f2)
    [ -z "$EXP_DATE_STR" ] && continue

    EXP_DATE_EPOCH=$(date -d "$EXP_DATE_STR" +%s 2>/dev/null)
    DAYS_LEFT=$(( (EXP_DATE_EPOCH - NOW) / 86400 ))

    PATH_LEN=${#cert}
    if [ "$PATH_LEN" -gt 55 ]; then
        DISPLAY_NAME="${cert:0:20}...${cert: -32}"
    else
        DISPLAY_NAME="$cert"
    fi

    if [ "$DAYS_LEFT" -lt 0 ]; then
        STATUS="\e[31m[CADUCADO]\e[0m"
        ALERT_REPORT="${ALERT_REPORT}❌ CADUCADO (Hace ${DAYS_LEFT#-} días): $cert\n"
    elif [ "$DAYS_LEFT" -lt 30 ]; then
        STATUS="\e[33m[ALERTA]\e[0m"
        ALERT_REPORT="${ALERT_REPORT}⚠️ ALERTA (Quedan $DAYS_LEFT días): $cert\n"
    else
        STATUS="\e[32m[OK]\e[0m"
    fi

    printf "%-55s | %-10s | %b\n" "$DISPLAY_NAME" "$DAYS_LEFT" "$STATUS"

# Aquí le pasamos el array completo a 'find'
done < <(find "${TARGETS[@]}" \( -type f -o -type l \) \( -name "*.pem" -o -name "*.crt" \) 2>/dev/null)

echo "-----------------------------------------------------------------------"
if [ -n "$ALERT_REPORT" ]; then
    echo "⚠️ Se encontraron certificados próximos a caducar. Enviando correo..."
    
    MAIL_CONTENT="To: $EMAIL_TO
From: $EMAIL_FROM
Subject: $SUBJECT
Content-Type: text/plain; charset=utf-8

Hola,

Se han detectado certificados que requieren acción inmediata en el servidor '$(hostname)':

$ALERT_REPORT
Por favor, proceda con la renovación.
"
    
    echo -e "$MAIL_CONTENT" | msmtp -t
    
    if [ $? -eq 0 ]; then
        echo -e "\e[32m[ÉXITO]\e[0m Correo de alerta enviado a Outlook."
    else
        echo -e "\e[31m[ERROR]\e[0m Falló el envío del correo."
    fi
else
    echo -e "\e[32m[OK]\e[0m Todos los certificados tienen más de 30 días. No se envía correo."
fi
