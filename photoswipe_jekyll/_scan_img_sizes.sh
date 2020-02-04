#!/bin/sh

echo "file,width,height" > _data/images.csv

cd img
identify -format "%f,%w,%h\n" *.jpg >> ../_data/images.csv
cd ..
