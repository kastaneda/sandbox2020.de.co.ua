IMAGE = my-static-site:v2

run: build
	docker run --rm -p 8080:80 $(IMAGE)

build:
	DOCKER_BUILDKIT=1 docker build -t $(IMAGE) .

clean:
	docker rmi $(IMAGE)

.PHONY: run build
