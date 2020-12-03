SIZES = 16 32 36 48 57 60 72 76 96 114 120 144 152 180 192 194 256 384 512
ICONS = $(patsubst %,%.png,$(SIZES))

all: $(ICONS)

%.png: circle.svg
	rsvg-convert $< -w $* -h $* -o $@
	optipng $@