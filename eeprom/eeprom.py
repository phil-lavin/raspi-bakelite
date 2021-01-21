#!/usr/bin/env python
import smbus
import time
import sys

# Config
bus = smbus.SMBus(0)   # I2C bus number
Dev_Addr = 0x50        # I2C device 7 bit address
debug = True           # Print debug info from this script

# Debug logger
def debug_log(s):
    if debug:
        print(s)

# Handle skipping to the correct part of the EEPROM
def skip(pos):
    # A 64Kb EEPROM has 8192 Bytes. We must break the 2 byte position (pos) into
    # the high and low bytes and pass them separately
    high = pos >> 8
    low = pos % 256

    bus.write_i2c_block_data(Dev_Addr, high, [low]) # Does the skipping

# Get number of atoms (2 byte, Little Endian)
skip(6) # Bytes 6 and 7 are the number of atoms
b1 = bus.read_byte(Dev_Addr)
b2 = bus.read_byte(Dev_Addr)
atoms = b2<<8 | b1
debug_log("We have " + str(atoms) + " atoms")

# Set our offset to the end of the header (i.e. start of first atom)
offset = 12

# Loop through each atom and get type and length
for i in range (0, atoms):
    debug_log("Analysing atom " + str(i))

    skip(offset) # Skip to start of this atom
    b1 = bus.read_byte(Dev_Addr)
    b2 = bus.read_byte(Dev_Addr)
    type = b2<<8 | b1
    debug_log("Type is " + str(type))

    skip(offset+4) # Skip to start of length
    b1 = bus.read_byte(Dev_Addr)
    b2 = bus.read_byte(Dev_Addr)
    len = b2<<8 | b1
    debug_log("Length is " + str(len)) # Length is of data+CRC. Total atom is 8 bytes bigger than this

    # Atom is type 4 (Manufacturer custom data)
    if type == 4:
        customData = ""
        skip(offset+8) # Skip to start of data

        # Read data (data length is len - 2 byte CRC)
        for j in range (0, len - 2):
            customData += chr(bus.read_byte(Dev_Addr))

        # Dump it
        print("Custom data at atom " + str(i) + ": " + customData)

    offset = offset + len + 8 # Set offset to start of next atom
    debug_log("New offset is " + str(offset))
