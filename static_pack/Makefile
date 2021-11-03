run: build
	docker run -p 8000:80 docker-nginx-test

build: pack
	docker build -t docker-nginx-test .

pack:
	find html/ -type f -regex '.*\.\(html\|css\|js\|xml\|txt\)$$' -exec brotli -fZ {} \; -exec zopfli --i20 {} \;

.PHONY: run build pack
