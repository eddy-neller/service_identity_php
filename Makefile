.DEFAULT_GOAL := help
help:
	@printf "\n \e[1;30m############################################################\e[0m\n\n"
	@printf "\e[1;30m To change the following variables please edit makefile.conf \e[0m\n";
	@printf "\n \e[1;30m############################################################\e[0m\n\n"
	@printf "\n"
	@printf "\e[3m	Usage limited to : E.N Shop API \e[0m\n\n";
	@printf "\n"
	@printf "\e[33m	Usage:\e[0m";
	@printf "   make [option]\n"

	@awk '{ \
			if ($$0 ~ /^.PHONY:/) { \
				helpCommand = substr($$0, index($$0, ":") + 2); \
				if (helpMessage) { \
					printf "\033[32m%-30s\033[0m %s\n", \
						helpCommand, helpMessage; \
					helpMessage = ""; \
				} \
			} else if ($$0 ~ /^##/) { \
				if (helpMessage) { \
					helpMessage = helpMessage"\n                               "substr($$0, 3); \
				} else { \
					helpMessage = substr($$0, 3); \
				} \
			} else { \
				if (helpMessage) { \
					print "\n"helpMessage"\n" \
				} \
				helpMessage = ""; \
			} \
		}' \
		$(MAKEFILE_LIST)
	@printf "\n\n"

#!make
include makefile.conf

## Install Project
.PHONY: install
install:
	@echo "$(YELLOW)** Starting installation... **$(RESET)"
	@echo "$(YELLOW)** Update Git Repository **$(RESET)"
	@git fa && git plr
	@echo "$(YELLOW)** Update Docker Images **$(RESET)"
	@make down-hard
	@echo "$(YELLOW)** Update Docker Images **$(RESET)"
	@docker pull postgres:18-alpine && docker pull rabbitmq:4-management-alpine && docker pull varnish:8-alpine && docker pull redis:8-alpine
	@echo "$(YELLOW)** Build & Load Docker Containers **$(RESET)"
	make binc && make up
	@echo "$(YELLOW)** Load composer install & dump-autoload **$(RESET)"
	@make ci && make cda
	@echo "$(YELLOW)** Manage DEV database **$(RESET)"
	@bin/console doctrine:database:create --if-not-exists && \
		bin/console doctrine:migrations:migrate --no-interaction
	@bin/console doctrine:fixtures:load --no-interaction --group=dev
	@echo "$(YELLOW)** Manage TEST database **$(RESET)"
	@bin/console doctrine:database:create -e test --if-not-exists && \
		bin/console doctrine:migrations:migrate -e test --no-interaction
	@bin/console doctrine:fixtures:load -e test --no-interaction --group=test
	@echo "$(YELLOW)** Load composer outdated & symfony:recipes **$(RESET)"
	@make co && make csr
	@echo "$(GREEN)** Installation completed!!! **$(RESET)"

##--------------------------------- Docker -----------------------------------

## Execute docker compose
.PHONY: dc
dc:
	@$(DOCKER)

## Crée et demarre les containers
.PHONY: up
up:
	@$(DOCKER) up -d --remove-orphans

## Stop et détruits les containers
.PHONY: down
down:
	@$(DOCKER) down --remove-orphans

## Stop et détruits les containers
.PHONY: down-hard
down-hard:
	@$(DOCKER) down --rmi all -v --remove-orphans

## Build les containers
.PHONY: bi
bi:
	@$(DOCKER) build

## Build les containers sans cache
.PHONY: binc
binc:
	@$(DOCKER) build --no-cache

## Connection au ssh du container db
.PHONY: bash-db
bash-db:
	@$(DOCKER) exec database bash

## Connection au ssh du container redis
.PHONY: bash-redis
bash-redis:
	@$(DOCKER) exec redis sh

## Connection au ssh du container varnish
.PHONY: bash-varnish
bash-varnish:
	@$(DOCKER) exec varnish sh

##--------------------------------- Composer -----------------------------------

## Execute composer
.PHONY: c
c:
	@composer

## Execute composer install
.PHONY: ci
ci:
	@composer install
	@find vendor/bin -name "*.bat" -delete

## Execute composer install
.PHONY: ci-dry
ci-dry:
	@composer install --dry-run

## Execute composer update
.PHONY: cu
cu:
	@composer update

## Execute composer update dry-run
.PHONY: cu-dry
cu-dry:
	@composer update --dry-run

## Execute composer outdated
.PHONY: co
co:
	@composer outdated

## Execute composer dump-autoload
.PHONY: cda
cda:
	@composer dump-autoload

## Execute composer require
.PHONY: creq $(p)
creq:
	@composer require ${p}

## Execute composer require --dev
.PHONY: creqdev $(p)
creqdev:
	@composer require --dev ${p}

## Execute composer remove
.PHONY: crem $(p)
crem:
	@composer remove ${p}

## Execute composer recipes
.PHONY: cr
cr:
	@composer recipes

## Execute composer symfony:recipes
.PHONY: csr
csr:
	@composer symfony:recipes

## Execute composer symfony:recipes:install
.PHONY: csri $(p)
csri:
	@composer symfony:recipes:install --force -v ${p}

## Execute composer version
.PHONY: cv
cv:
	@composer -V

##--------------------------------- Symfony -----------------------------------

## Démarre le Symfony Server
.PHONY: serve-start
serve-start:
	@$(SYMFONY) server:start --allow-http --listen-ip=0.0.0.0 --port=20900 --no-tls -d

## Affiche les logs du Symfony Server
.PHONY: serve-log
serve-log:
	@$(SYMFONY) server:log

## Stoppe le Symfony Server
.PHONY: serve-stop
serve-stop:
	@$(SYMFONY) server:stop

## Redémarre le Symfony Server
.PHONY: serve-restart
serve-restart:
	@make serve-stop && make serve-start

## Consomme les messages du transport async (Messenger)
.PHONY: messenger-consume
messenger-consume:
	@$(APP) sh -c "bin/console messenger:consume async -vv"

##--------------------------------- Tests -----------------------------------

## Run grum tests
.PHONY: grumphp
grumphp:
	@$(APP) sh -c "vendor/bin/grumphp run"

## Run GrumPHP on staged files
.PHONY: grumphp-cm
grumphp-cm:
	@$(APP) sh -c "vendor/bin/grumphp git:pre-commit"

## Run phpunit tests
.PHONY: unit
unit:
	@$(APP) sh -c "vendor/bin/phpunit --display-warnings --display-deprecations --display-phpunit-deprecations --display-notices"

## Run tests for a method or class (Ex: make unit-filter f=AuthenticationFailureListenerTest)
.PHONY: unit-filter $(f)
unit-filter:
	@$(APP) sh -c "vendor/bin/phpunit --filter ${f} --display-warnings --display-deprecations --display-phpunit-deprecations --display-notices"

## Execute a suite of tests, by setting testsuite name (Ex: make unit-suite n=api.gallery)
.PHONY: unit-suite $(n)
unit-suite:
	@$(APP) sh -c "vendor/bin/phpunit --testsuite ${s} --display-warnings --display-deprecations --display-phpunit-deprecations --display-notices"

## Run PHPUnit with code coverage (generates HTML report in coverage/)
.PHONY: unit-coverage
unit-coverage:
	@$(APP) sh -c "XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html coverage/"

## Run phpstan tests
.PHONY: stan
stan:
	@$(APP) sh -c "vendor/bin/phpstan analyse"

## Run phpcs tests
.PHONY: phpcs
phpcs:
	@$(APP) sh -c "vendor/bin/phpcs"

## Run phpcs tests with details
.PHONY: phpcs_det
phpcs_det:
	@$(APP) sh -c "vendor/bin/phpcs -s"

## Run phpspec tests summary
.PHONY: phpcs_sum
phpcs_sum:
	@$(APP) sh -c "vendor/bin/phpcs --report-summary"

## Run phpcsfixer tests
.PHONY: phpcsfixer
phpcsfixer:
	@$(APP) sh -c "vendor/bin/php-cs-fixer"

## Run phpcsfixer dry-run tests
.PHONY: phpcsfixer_dry
phpcsfixer_dry:
	@$(APP) sh -c "vendor/bin/php-cs-fixer fix --dry-run --diff"

## Run phpcsfixer fix tests
.PHONY: phpcsfixer_fix
phpcsfixer_fix:
	@$(APP) sh -c "vendor/bin/php-cs-fixer fix ${f}"

## Run phpmd tests
.PHONY: phpmd
phpmd:
	@$(APP) sh -c "vendor/bin/phpmd application,domain,infrastructure,presentation,src text ruleset.xml"

## Run rector
.PHONY: rector
rector:
	@$(APP) sh -c "vendor/bin/rector"

## Run rector dry-run
.PHONY: rector-dry
rector-dry:
	@$(APP) sh -c "vendor/bin/rector --dry-run"

##--------------------------------- Autres -----------------------------------

## Fix owner of project
.PHONY: set-owner
set-owner:
	@chown -R venom .

## Fix permissions of all public files
.PHONY: fix-perms
fix-perms:
	@chmod -R 777 public; chmod -R 777 var

## Purge les dossiers cache and logs
.PHONY: purge
purge:
	@rm -rf var/cache/ var/log/

%:
	@:
