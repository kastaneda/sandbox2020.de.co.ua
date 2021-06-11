PHOTO_SRC := $(wildcard src/*.jpg)

JPG = $(patsubst src/%.jpg,img/%.jpg,$(PHOTO_SRC))
JPG_300 = $(patsubst src/%.jpg,img/%.300.jpg,$(PHOTO_SRC))
JPG_1200 = $(patsubst src/%.jpg,img/%.1200.jpg,$(PHOTO_SRC))
WEBP = $(patsubst %.jpg,%.webp,$(JPG) $(JPG_300) $(JPG_1200))
IMG_ALL = $(JPG) $(JPG_300) $(JPG_1200) $(WEBP)

all: $(IMG_ALL) pswp

img/%.300.jpg: src/%.jpg
	@mkdir -p img
	convert $< -gravity center -thumbnail '400x400>' -thumbnail 300x300^ $@

img/%.1200.jpg: src/%.jpg
	@mkdir -p img
	convert $< -thumbnail 1200x1200^ $@

img/%.jpg: src/%.jpg
	@mkdir -p img
	cp $< $@

%.webp: %.jpg
	cwebp $< -o $@

clean:
	rm -rf img

pswp:
	svn checkout https://github.com/dimsemenov/PhotoSwipe/trunk/dist $@

.PHONY: all clean
