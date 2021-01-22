Raspberry Pi Bakelite Phone VoIP Interface Board - EEPROM
=========================================================

## Intro

It is a requirement of [Pi Hats](https://github.com/raspberrypi/hats) to have an EEPROM chip at least 32Kb in size connected to the ID (ID_SC and ID_SD) pins of the Pi.

The EEPROM chip should contain a number of components, in a [defined format](https://github.com/raspberrypi/hats/blob/master/eeprom-format.md), which describe the Hat, its GPIO usage and its Linux device tree. You can also include custom data.

## Files

Below is a list of the files in this directory and a description of them:

* custom.txt: The custom data that gets flashed to the end of the EEPROM. This can be read, with some effort, by your applications
* dtc: Latest (at time of writing) version of the [Device Tree Compiler Toolchain](https://github.com/dgibson/dtc) as the version shipped on the Pi is buggy and segfaults when examining the Pi. This was compiled on a Pi Model B+ running Buster
* eeprom.py: A small bit of Python example code, demonstrating traversing the EEPROM's structure and pulling out the custom data written to the end of it
* eeprom_settings.txt: Settings file which is used to construct the main parts of the EEPROM's data
* flasheep.sh: Wrapper script around other tools to flash the EEPROM
* hats/: Git submodule of [Pi Hats](https://github.com/raspberrypi/hats) which contains the tools to build and flash the EEPROM data
* raspi-bakelite.dts: Device Tree definition file for the hat (described in much more depth below!)

## Flashing the EEPROM

The EEPROM running on this Hat is a 24LC64 64Kb chip. The Pi Model B+ has 2 I2C busses, numbered 0 and 1. Bus 0 is the ID pins (27 and 28) and bus 1 is the SDA/SCL pins (3 and 5).

The Hat specification states that the I2C address of the EEPROM must be 0x50.

The process of flashing the EEPROM is:

* Compile the raspi-bakelie.dts file using dtc
* Build the EEPROM data image from the settings file, device tree and custom data files
* Flatten the EEPROM by writing 32Kb of 0s to it
* Write the data image we built to the chip

Flashing is done using the flasheep.sh wrapper script:

```
root@roto-voip:~/raspi-bakelite/eeprom# ./flasheep.sh 
raspi-bakelite.dts:63.24-67.19: Warning (unit_address_vs_reg): /fragment@2/__overlay__/trig/trig@4: node has a unit name, but no reg or ranges property
raspi-bakelite.dts:77.24-81.19: Warning (unit_address_vs_reg): /fragment@2/__overlay__/dial/dial@5: node has a unit name, but no reg or ranges property
raspi-bakelite.dts:91.24-95.19: Warning (unit_address_vs_reg): /fragment@2/__overlay__/hang/hang@6: node has a unit name, but no reg or ranges property
raspi-bakelite.dts:55.24-68.15: Warning (avoid_unnecessary_addr_size): /fragment@2/__overlay__/trig: unnecessary #address-cells/#size-cells without "ranges" or child "reg" property
raspi-bakelite.dts:69.24-82.15: Warning (avoid_unnecessary_addr_size): /fragment@2/__overlay__/dial: unnecessary #address-cells/#size-cells without "ranges" or child "reg" property
raspi-bakelite.dts:83.24-96.15: Warning (avoid_unnecessary_addr_size): /fragment@2/__overlay__/hang: unnecessary #address-cells/#size-cells without "ranges" or child "reg" property
Opening file eeprom_settings.txt for read
Done reading
Opening DT file raspi-bakelite.dtb for read
Adding 2819 bytes of DT data
Opening custom data file custom.txt for read
Adding 53 bytes of custom data
Writing out...
Writing out DT...
Done.
This will attempt to talk to an eeprom at i2c address 0x50 on bus 0. Make sure there is an eeprom at this address.
This script comes with ABSOLUTELY no warranty. Continue only if you know what you are doing.
Writing...
4096 bytes (4.1 kB, 4.0 KiB) copied, 22 s, 0.2 kB/s
8+0 records in
8+0 records out
4096 bytes (4.1 kB, 4.0 KiB) copied, 22.4986 s, 0.2 kB/s
Closing EEPROM Device.
Done.
This will attempt to talk to an eeprom at i2c address 0x50 on bus 0. Make sure there is an eeprom at this address.
This script comes with ABSOLUTELY no warranty. Continue only if you know what you are doing.
Writing...
2560 bytes (2.6 kB, 2.5 KiB) copied, 14 s, 0.2 kB/s
5+1 records in
5+1 records out
3010 bytes (3.0 kB, 2.9 KiB) copied, 16.5093 s, 0.2 kB/s
Closing EEPROM Device.
Done.
```

## EEPROM Settings

The eeprom_settings.txt file is pretty self explanatory, if you look at it. It defines the name and vendor of the board, that the board provides at least 2A of power to the Pi and it sets ip the 7 GPIO pins used by the Pi.

The custom data in this settings file appears to be limited to 3 bytes. Instead, you should use the custom.txt file to add custom data to the EEPROM which is only limited by the size of the ROM.

You can ensure the GPIO settings have applied with `raspi-gpio get`:

```
root@roto-voip:~/raspi-bakelite# raspi-gpio get | grep 'GPIO [456]:\|GPIO 1[89]\|GPIO 2[01]'
GPIO 4: level=0 fsel=0 func=INPUT
GPIO 5: level=0 fsel=0 func=INPUT
GPIO 6: level=0 fsel=0 func=INPUT
GPIO 18: level=0 fsel=1 func=OUTPUT
GPIO 19: level=0 fsel=1 func=OUTPUT
GPIO 20: level=0 fsel=1 func=OUTPUT
GPIO 21: level=1 fsel=1 func=OUTPUT
```

## EEPROM Custom Data

The custom data can be read off the EEPROM, with some effort, by understanding the [defined format](https://github.com/raspberrypi/hats/blob/master/eeprom-format.md) of the data.

The eeprom.py script in this directory gives a basic example of traversing the data and printing out any Atoms of type 0x0004 (manufacturer custom data).

```
root@roto-voip:~/raspi-bakelite/eeprom# python eeprom.py 
We have 4 atoms
Analysing atom 0
Type is 1
Length is 58
New offset is 78
Analysing atom 1
Type is 2
Length is 32
New offset is 118
Analysing atom 2
Type is 3
Length is 2821
New offset is 2947
Analysing atom 3
Type is 4
Length is 55
Custom data at atom 3: trig=4;dial=5;hang=6;ring=18;led1=19;led2=20;led3=21
```

## Device Tree

This is where things get complicated, and very poorly documented :/

A few useful references:

* https://mjoldfield.com/atelier/2017/03/rpi-devicetree.html
* https://developer.aliyun.com/mirror/npm/package/gpio-button/v/0.1.0
* https://github.com/torvalds/linux/blob/v4.19/include/uapi/linux/input-event-codes.h#L341
* https://www.kernel.org/doc/Documentation/devicetree/bindings/input/gpio-keys.txt
* https://learn.sparkfun.com/tutorials/reading-and-writing-serial-eeproms/all
* https://www.raspberrypi.org/documentation/configuration/device-tree.md

The raspi-bakelite.dts file defines the changes to the Linux Device Tree that should be made when the Pi boots.

It defines a few things:

### 3x LEDs

This maps the GPIO pins used by the LEDs on the Hat to /sys/class/leds/. This is useful because the userland code can now address named LEDs without having to know the pin numbers.

Turning on/off the LEDs is a simple as writing 1 or 0 to the brightness file handle. E.g:

```
echo 1 > /sys/class/leds/bakelite-green/brightness
```

This turns on the green LED.

### 3x "Button" Inputs

These are the 3 physical switch inputs from the phone (dialing triggered, dialling and hang up). These are mapped to devices in /dev/input/by-path/ and also key presses 
on what is, effectively, a keyboard. Again, this is useful because we can respond to these named events without knowing the pin numbers.

You can use the evtest Linux tool to monitor the devices and report updates:

```
root@roto-voip:~/raspi-bakelite# evtest /dev/input/by-path/platform-soc\:trig-event 
Input driver version is 1.0.1
Input device ID: bus 0x19 vendor 0x1 product 0x1 version 0x100
Input device name: "soc:trig"
Supported events:
  Event type 0 (EV_SYN)
  Event type 1 (EV_KEY)
    Event code 260 (BTN_4)
Properties:
Testing ... (interrupt to exit)
Event: time 1611266120.098545, type 1 (EV_KEY), code 260 (BTN_4), value 1
Event: time 1611266120.098545, -------------- SYN_REPORT ------------
Event: time 1611266121.438556, type 1 (EV_KEY), code 260 (BTN_4), value 0
Event: time 1611266121.438556, -------------- SYN_REPORT ------------
```

### Ringer Output

This is a GPIO output which, via a transistor and basic 5v inverter, drives the INHIBIT pin on the ring generator. When this pin is HIGH, the phone rings, when it is LOW the phone stops 

@TODO: Figure out and document this
