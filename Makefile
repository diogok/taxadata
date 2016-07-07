all: build

build:
	docker build -t diogok/taxadata .

push:
	docker push diogok/taxadata
