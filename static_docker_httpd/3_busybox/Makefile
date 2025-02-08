IMAGE = my-static-site:v3

run: build
	docker run -it --rm -p 8080:80 $(IMAGE)

build:
	docker build -t $(IMAGE) .

clean:
	docker rmi $(IMAGE)

.PHONY: run build
