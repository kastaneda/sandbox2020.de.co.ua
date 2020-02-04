IMAGES = $(shell find img/ -type f -name '*.jpg')
THUMBNAILS = $(patsubst img/%,thumbnail/%,$(IMAGES))

build: _data/images.csv thumbnail $(THUMBNAILS)
	jekyll build

thumbnail:
	mkdir $@

thumbnail/%.jpg: img/%.jpg
	convert $< -resize 200x200 $@

_data/images.csv: $(IMAGES)
	echo "file,width,height" > $@
	identify -format "%f,%w,%h\n" $(IMAGES) >> $@


.PHONY: build
