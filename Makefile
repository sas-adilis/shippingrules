.PHONY: prepare

fix :
	composer i && php vendor/bin/php-cs-fixer fix && composer i --no-dev && rm -rf vendor
