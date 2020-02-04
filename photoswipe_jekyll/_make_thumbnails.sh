#!/bin/sh

mkdir -p img/thumbnail

for FILE in img/*.jpg
do
    THUMBNAIL=img/thumbnail/`basename $FILE`
    if [ ! -f $THUMBNAIL -o $THUMBNAIL -ot $FILE ]
    then
        echo "$FILE -> $THUMBNAIL"
        convert $FILE -resize 200x200 $THUMBNAIL
    fi
done
