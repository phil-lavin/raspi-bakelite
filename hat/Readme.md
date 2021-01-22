Raspberry Pi Bakelite Phone VoIP Interface Board - Hat Designs
=========================================================

## Intro

The Bakelite Interface Board is designed to the functional and [mechanical specifications](https://github.com/raspberrypi/hats/blob/master/hat-board-mechanical.pdf) of a [Pi Hats](https://github.com/raspberrypi/hats).

This directory contains the Eagle cam files (schematic, board and gerbers... and image exports thereof) for the board.

## Schematic

Here is the Eagle schematic for the hat:

![Eagle Schematic](https://raw.githubusercontent.com/phil-lavin/raspi-bakelite/master/hat/Bakelite%20Interface%20Board%20v1.1%20Schematic.png)

The actual Eagle schematic file is also in this directory (the `.sch` file).

The schematic looks complicated but it's actually just a bunch of simple circuits all stitched together. These are:

### 12v Input (Middle Left of Schematic)

This is just a 2 pin screw header which takes an external 12V input (`VCC_2`) and puts it through a 2A resettable Polyfuse to create `VCC_2_FUSED`.

### 5v Power (Top Left of Schematic)

This takes the fused 12V output (`VCC_2_FUSED`) and passes it through a 5V regulator and a 2A resettable Polyfuse. This is then fed into the 5V pin of the Pi, 
thus providing power to the Pi from the 12V input.

The schematic notes that the regulator is a `788TV` (5V/2A Linear Regulator) but I will actually use the `K7805-2000` which is a pin compatible switching regulator.
It's more expensive but it's more efficient and runs a lot less hot, such that it doesn't need a heatsink.

The capacitors are just to ensure a smooth power feed is received.

### Ringer Control (Bottom Left of Schematic)

I am using a pre-packaged ring generator which is external from the board. This is a PowerDsine `PCR-SIN03V12F20-C`. It has a 6 pin Molex `2502` connector. This connector 
connects to the 6 pin connector on the board (labelled `RINGER-1` to `RINGER-6`).

The board also has a standard 10 pin header on it. The Bakelite's original ringer connects to this (pins 7 and 9).

The `PCR-SIN03V12F20-C` ringer module takes a 12V power and GND input (pins 3 and 4) and it outputs to the Bakelite's original ringer (pins 1 and 2). Pin 6 of the ringer module is the `INHIBIT` pin. This 
pin takes a 5V logic input and when it's held high, the ringer stops outputting. When the pin is held low, the ringer outputs (and thus the bell rings). Because the Pi 
doesn't have any 5V GPIOs, this is fed via a transistor and simple inverter circuit. Under normal operation, the pin is tied directly to the 5V line via a 10K resistor. 
When the Pi's GPIO output goes high, this "opens up" the transistor and pulls the `INHIBIT` pin to GND. This makes the bell ring.

In essence, when the Pi's GPIO is high, the bell rings. When it's low, it doesn't.

### "Switch" Inputs (Centre of Schematic)

The Bakelite phone has 3 switch-like inputs which we are interested in. All of these start in the on state. 

Two of the switch-like inputs are from the rotary dialler. One of them (named `TRIG` in the schematic) turns off when you start dialling and it turns back on when you stop dialling.
The next (named `DIAL`) in the schematic turns off and on again based on the number you dialled. This generates pulses - so if you dialled 5, you'd get 5 pulses.

The other switch-like input is not part of the original phone. Rather, it is a Long Lever Microswitch which has been attached to the phone just below the cradle that holds the handset.
When the handset is down, this switch is pressed (i.e. on). When the handset is lifted up, the switch turns off. This is connected to the `HANG` input on the schematic.

Each of these switches are connected to a GPIO pin on the Pi through a simple pulldown circuit with an added capacitor for debouncing the switch input. The GPIO pins are normally pulled 
to GND, until the switch is turned on when they are pulled to 3.3V.

### EEPROM Circuit (Top Right of Schematic)

It is a requirement of [Pi Hats](https://github.com/raspberrypi/hats) to have an EEPROM chip at least 32Kb in size connected to the ID (`ID_SC` and `ID_SD`) pins of the Pi.

The EEPROM chip should contain a number of components, in a [defined format](https://github.com/raspberrypi/hats/blob/master/eeprom-format.md), which describe the Hat, its GPIO usage and its Linux device tree. You can also include custom data.

This is much better documented in the eeprom folder of this repository.

### LEDs (Bottom Right of Schematic)

The board contains 3 LEDs, each connected via a resistor, to the Pi's GPIO pins. These can be used by the userland code to provide some sort of status and diagnostic output.

## Gerbers

The Gerber CAM files which have been built to the Design Rules of [Elecrow](https://www.elecrow.com/pcb-manufacturing.html) are in the gerbers directory.

This is what a rendering of the top and bottom of the board looks like:

### Top

<img src="https://raw.githubusercontent.com/phil-lavin/raspi-bakelite/master/hat/gerbers/images/top.svg" width="100%" alt="Top of Board">

### Bottom

<img src="https://raw.githubusercontent.com/phil-lavin/raspi-bakelite/master/hat/gerbers/images/bottom.svg" width="100%" alt="Bottom of Board">

## Board

Here is the Eagle board for the hat:

![Eagle Schematic](https://raw.githubusercontent.com/phil-lavin/raspi-bakelite/master/hat/Bakelite%20Interface%20Board%20v1.1%20Board.png)

The actual Eagle board file is also in this directory (the `.brd` file).
