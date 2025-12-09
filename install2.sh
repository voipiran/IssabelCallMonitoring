#!/bin/bash

# اسکریپت نصب هوشمند ماژول Control Panel (فارسی / انگلیسی)
# توسعه یافته توسط VoipIran.io

clear
# Colorize output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
MAGENTA='\033[1;35m'
BOLD='\033[1m'
NC='\033[0m' # No color
echo -e "${MAGENTA}###############################################################${NC}"
echo -e "${CYAN}██╗   ██╗ ██████╗ ██╗██████╗ ██╗██████╗  █████╗ ███╗   ██╗${NC}"
echo -e "${CYAN}██║   ██║██╔═══██╗██║██╔══██╗██║██╔══██╗██╔══██╗████╗  ██║${NC}"
echo -e "${CYAN}██║   ██║██║   ██║██║██████╔╝██║██████╔╝███████║██╔██╗ ██║${NC}"
echo -e "${CYAN}╚██╗ ██╔╝██║   ██║██║██╔═══╝ ██║██╔══██╗██╔══██║██║╚██╗██║${NC}"
echo -e "${CYAN} ╚████╔╝ ╚██████╔╝██║██║     ██║██║  ██║██║  ██║██║ ╚████║${NC}"
echo -e "${CYAN}  ╚═══╝   ╚═════╝ ╚═╝╚═╝     ╚═╝╚══╝  ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝${NC}"
echo -e "${MAGENTA}###############################################################${NC}"
echo -e "${MAGENTA}                    https://voipiran.io                    ${NC}"
echo -e "${MAGENTA}###############################################################${NC}"
echo ""
echo "لطفاً زبان مورد نظر خود را انتخاب کنید:"
echo ""
echo "1) فارسی (Persian) - نسخه کاملاً فارسی و راست‌چین"
echo "2) English - LTR version"
echo ""
read -p "انتخاب شما (1 یا 2): " choice

# پاک کردن نصب قبلی (برای اطمینان از نصب تمیز)
echo ""
echo "[1/4] در حال پاک‌سازی نصب قبلی..."
rm -rf /var/www/html/modules/control_panel

# انتخاب نسخه
case $choice in
    1|"")
        echo "[2/4] نصب نسخه فارسی..."
        cp -ar control_panel/ /var/www/html/modules/control_panel
        echo "نسخه فارسی با موفقیت نصب شد."
        ;;
    2)
        echo "[2/4] نصب نسخه انگلیسی..."
        cp -ar control_panel_en/ /var/www/html/modules/control_panel
        echo "نسخه انگلیسی با موفقیت نصب شد."
        ;;
    *)
        echo "انتخاب نامعتبر! نسخه فارسی نصب می‌شود."
        cp -ar control_panel/ /var/www/html/modules/control_panel
        ;;
esac

# اعمال منوها در Issabel
echo "[3/4] در حال اعمال منوها در Issabel..."
yes | issabel-menumerge control.xml > /dev/null 2>&1

# تنظیم دسترسی‌ها
echo "[4/4] تنظیم دسترسی‌های فایل..."
chown -R asterisk:asterisk /var/www/html/modules/control_panel
chmod -R 755 /var/www/html/modules/control_panel

# ریستارت وب سرور (اختیاری اما توصیه می‌شود)
echo ""
echo "نصب با موفقیت انجام شد!"
echo ""
echo "برای اعمال کامل تغییرات، لطفاً این دستور را اجرا کنید:"
echo "   fwconsole restart"
echo ""
echo "یا سرور رو ریبوت کنید."
echo ""
echo "پنل در منوی سمت چپ → Control Panel در دسترس است."
echo ""
echo "توسعه‌یافته با ❤️ توسط VoipIran.io"
echo "https://voipiran.io"
echo ""

exit 0