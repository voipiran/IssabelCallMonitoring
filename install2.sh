#!/bin/bash
clear

RED='\033[0;31m'
GREEN='\033[0;32m'
MAGENTA='\033[1;35m'
BOLD='\033[1m'
NC='\033[0m'

echo -e "${MAGENTA}###############################################################${NC}"
echo -e "${CYAN}██╗ ██╗ ██████╗ ██╗██████╗ ██╗██████╗ █████╗ ███╗ ██╗${NC}"
echo -e "${CYAN}██║ ██║██╔═══██╗██║██╔══██╗██║██╔══██╗██╔══██╗████╗ ██║${NC}"
echo -e "${CYAN}██║ ██║██║   ██║██║██████╔╝██║██████╔╝███████║██╔██╗ ██║${NC}"
echo -e "${CYAN}╚██╗██╔╝██║   ██║██║██╔═══╝ ██║██╔══██╗██╔══██║██║╚██╗██║${NC}"
echo -e "${CYAN} ╚████╔╝ ╚██████╔╝██║██║     ██║██║  ██║██║  ██║╚████╔╝${NC}"
echo -e "${CYAN}  ╚═══╝   ╚═════╝ ╚═╝╚═╝     ╚═╝╚═╝  ╚═╝╚═╝  ╚═╝ ╚═══╝${NC}"
echo -e "${MAGENTA}###############################################################${NC}"
echo -e "${MAGENTA}                https://voipiran.io                  ${NC}"
echo -e "${MAGENTA}###############################################################${NC}"
echo ""

# سوال ساده و بدون هیچ پیچیدگی
echo -e "${BOLD}نسخه مورد نظر خود را انتخاب کنید:${NC}"
echo ""
echo -e "${GREEN}1)${NC} فارسی (پیش‌فرض)"
echo -e "${GREEN}2)${NC} English"
echo ""
read -p "انتخاب (1 یا 2): " lang
echo ""

# اگر چیزی نزد یا 1 زد → فارسی
if [[ "$lang" != "2" ]]; then
    echo -e "${GREEN}در حال نصب نسخه فارسی...${NC}"
    rm -rf /var/www/html/modules/control_panel
    cp -ar control_panel/ /var/www/html/modules/control_panel
else
    echo -e "${GREEN}در حال نصب نسخه انگلیسی...${NC}"
    rm -rf /var/www/html/modules/control_panel
    cp -ar control_panel_en/ /var/www/html/modules/control_panel
fi

# بقیه کارها
yes | issabel-menumerge control.xml > /dev/null 2>&1
chown -R asterisk:asterisk /var/www/html/modules/control_panel
chmod -R 755 /var/www/html/modules/control_panel

echo ""
echo -e "${MAGENTA}╔══════════════════════════════════════╗${NC}"
echo -e "${MAGENTA}║        نصب با موفقیت انجام شد!       ║${NC}"
echo -e "${MAGENTA}╚══════════════════════════════════════╝${NC}"
echo ""
echo -e "برای اعمال تغییرات اجرا کنید:"
echo -e "   ${BOLD}fwconsole restart${NC}"
echo ""
echo -e "توسعه‌یافته با ${RED}♥${NC} توسط VoipIran.io"
echo -e "https://voipiran.io"
echo ""