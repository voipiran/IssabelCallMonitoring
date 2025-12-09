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

# حلقه تا وقتی کاربر درست جواب بده
while true; do
    echo -e "${BOLD}لطفاً زبان مورد نظر خود را انتخاب کنید:${NC}"
    echo ""
    echo -e "   ${GREEN}1)${NC} فارسی (Persian) - نسخه کاملاً فارسی و راست‌چین"
    echo -e "   ${GREEN}2)${NC} English - LTR version"
    echo ""
    read -p "انتخاب شما (1 یا 2) [پیش‌فرض: 1]: " choice

    # اگر فقط Enter زد → فارسی
    if [[ -z "$choice" ]]; then
        choice="1"
    fi

    # مقایسه بدون نقل قول (درست)
    case $choice in
        1|۱)
            echo -e "\n${GREEN}نسخه فارسی انتخاب شد.${NC}\n"
            VERSION="fa"
            break
            ;;
        2|۲)
            echo -e "\n${GREEN}نسخه انگلیسی انتخاب شد.${NC}\n"
            VERSION="en"
            break
            ;;
        *)
            echo -e "\n${RED}خطا: لطفاً فقط عدد 1 یا 2 را وارد کنید!${NC}\n"
            sleep 1
            ;;
    esac
done

# ادامه نصب (دقیقاً مثل قبل)
echo "[1/4] در حال پاک‌سازی نصب قبلی..."
rm -rf /var/www/html/modules/control_panel

echo "[2/4] در حال نصب نسخه انتخابی..."
if [ "$VERSION" = "en" ]; then
    cp -ar control_panel_en/ /var/www/html/modules/control_panel
    echo -e "${GREEN}نسخه انگلیسی با موفقیت نصب شد.${NC}"
else
    cp -ar control_panel/ /var/www/html/modules/control_panel
    echo -e "${GREEN}نسخه فارسی با موفقیت نصب شد.${NC}"
fi

echo "[3/4] در حال اعمال منوها در Issabel..."
yes | issabel-menumerge control.xml > /dev/null 2>&1

echo "[4/4] تنظیم دسترسی‌های فایل..."
chown -R asterisk:asterisk /var/www/html/modules/control_panel
chmod -R 755 /var/www/html/modules/control_panel

echo ""
echo -e "${MAGENTA}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║                نصب با موفقیت انجام شد!                      ║${NC}"
echo -e "${MAGENTA}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "برای اعمال تغییرات:"
echo -e "   ${BOLD}fwconsole restart${NC}"
echo ""
echo -e "پنل در منوی سمت چپ → ${BOLD}Control Panel${NC} در دسترس است."
echo ""
echo -e "توسعه‌یافته با ${RED}♥${NC} توسط ${BOLD}VoipIran.io${NC}"
echo -e "https://voipiran.io"
echo ""

exit 0