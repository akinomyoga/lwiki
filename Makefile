# -*- mode:makefile-gmake -*-

all:
.PHONY: all dist install

dist:
	cd .. && tar cavf lwiki.`date +'%Y%m%d'`.tar.xz lwiki --exclude=*~ --exclude=/backup
