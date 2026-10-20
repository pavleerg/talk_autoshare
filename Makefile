app_name := talk_autoshare
version := $(shell sed -n 's:.*<version>\(.*\)</version>.*:\1:p' appinfo/info.xml)
build_dir := build

.PHONY: test appstore clean

test:
	composer test

# Release tarball for production (no dev deps, no tests).
appstore: clean
	mkdir -p $(build_dir)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		--exclude=$(build_dir) --exclude=vendor --exclude=tests --exclude='composer.*' \
		--exclude=Makefile --exclude=.git --exclude=.gitignore --exclude=.phpunit.cache \
		-C .. $(app_name)
	@echo "Built $(build_dir)/$(app_name)-$(version).tar.gz"

clean:
	rm -rf $(build_dir)
