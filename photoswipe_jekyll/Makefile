IMAGES = $(shell find img/ -type f -name '*.jpg')
THUMBNAILS = $(patsubst img/%,thumbnail/%,$(IMAGES))
WEBP = $(patsubst %.jpg,%.webp,$(IMAGES))

build: _data/images.csv $(THUMBNAILS) $(WEBP)
	jekyll build

thumbnail/%.jpg: img/%.jpg
	mkdir -p $(@D)
	convert $< -thumbnail 200x200^ $@

%.webp: %.jpg
	cwebp $< -o $@

_data/images.csv: $(IMAGES)
	echo "file,width,height" > $@
	identify -format "%d/%f,%w,%h\n" $(IMAGES) >> $@

.PHONY: build
