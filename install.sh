#!/bin/bash

yes | cp -ar control_panel/ /var/www/html/modules
yes | issabel-menumerge control.xml

