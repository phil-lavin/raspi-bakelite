#!/usr/bin/env bash

dir=$(realpath $(dirname $0))
cd $dir
git submodule update --init

if [ ! -f hats/eepromutils/eepmake ] ; then
	cd hats/eepromutils
	make
	cd $dir
fi

./dtc -@ -I dts -O dtb -o raspi-bakelite.dtb raspi-bakelite.dts

if [ ! -f ./blank.eep ] ; then
	dd if=/dev/zero of=./blank.eep ibs=1K count=4
fi

hats/eepromutils/eepmake eeprom_settings.txt raspi-bakelite.eep raspi-bakelite.dtb -c custom.txt
yes yes | hats/eepromutils/eepflash.sh -w -f=blank.eep -t=24c64 -a=50 -d=0
yes yes | hats/eepromutils/eepflash.sh -w -f=raspi-bakelite.eep -t=24c64 -a=50 -d=0
