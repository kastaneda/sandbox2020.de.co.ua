IMAGES = $(shell find img/ -type f -name '*.jpg')
THUMBNAILS = $(patsubst img/%,thumbnail/%,$(IMAGES))

build: _data/images.csv $(THUMBNAILS)
	jekyll build

thumbnail/%.jpg: img/%.jpg
	mkdir -p $(@D)
	convert $< -resize 200x200 $@

_data/images.csv: $(IMAGES)
	echo "file,width,height" > $@
	identify -format "%d/%f,%w,%h\n" $(IMAGES) >> $@


.PHONY: build
