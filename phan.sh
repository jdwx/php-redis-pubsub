#!/bin/sh
time php -c .phan/php.ini ${HOME}/bin/phan >phan.txt
wc -l phan.txt

