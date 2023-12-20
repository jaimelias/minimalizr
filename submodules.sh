#!/bin/bash

# Change directory to "submodules"
cd submodules

rm -rf dy-core-master dy-core

# Download the archive from GitHub
curl -LJO "https://github.com/jaimelias/dy-core/archive/master.tar.gz"

# Extract the contents of the archive
tar -zxvf dy-core-master.tar.gz

# Remove the downloaded archive
rm dy-core-master.tar.gz

# Rename the extracted folder to "dy-core"
mv -f dy-core-master dy-core