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
export COMPOSE_BAKE = true

## Install Project
.PHONY: install
install:
	@echo "$(YELLOW)** Starting installation... **$(RESET)"
	@echo "$(YELLOW)** Update Git Repository **$(RESET)"
	@git fa && git plr
	@echo "$(YELLOW)** Destroy Docker Containers **$(RESET)"
	@make down-hard
	@echo "$(YELLOW)** Update Docker Images **$(RESET)"
	@docker pull php:8.4-fpm && docker pull nginx:1-alpine && docker pull postgres:18-alpine && docker pull rabbitmq:4-management-alpine && docker pull varnish:8-alpine && docker pull redis:8-alpine
	@echo "$(YELLOW)** Build & Load Docker Containers **$(RESET)"
	make binc && make up
	@echo "$(YELLOW)** Load composer install & dump-autoload **$(RESET)"
	@make ci && make cda
	@echo "$(YELLOW)** Manage DEV database **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:database:create --if-not-exists && \
		bin/console doctrine:migrations:migrate --no-interaction"
	@$(APP) sh -c "bin/console doctrine:fixtures:load --no-interaction --group=dev"
	@echo "$(YELLOW)** Manage TEST database **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:database:create -e test --if-not-exists && \
		bin/console doctrine:migrations:migrate -e test --no-interaction"
	@$(APP) sh -c "bin/console doctrine:fixtures:load -e test --no-interaction --group=test"
	@echo "$(YELLOW)** Load composer outdated & symfony:recipes **$(RESET)"
	@make co && make csr
	@echo "$(GREEN)** Installation completed!!! **$(RESET)"

## Re-install databases (dev + test) without rebuilding the containers
.PHONY: reinstall
reinstall:
	@echo "$(YELLOW)** Starting re-installation... **$(RESET)"
	@echo "$(YELLOW)** Manage DEV database **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:database:drop --force --if-exists && \
		bin/console doctrine:database:create --if-not-exists && \
		bin/console doctrine:migrations:migrate --no-interaction"
	@$(APP) sh -c "bin/console doctrine:fixtures:load --no-interaction --group=dev"
	@echo "$(YELLOW)** Manage TEST database **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:database:drop -e test --force --if-exists && \
		bin/console doctrine:database:create -e test --if-not-exists && \
		bin/console doctrine:migrations:migrate -e test --no-interaction"
	@$(APP) sh -c "bin/console doctrine:fixtures:load -e test --no-interaction --group=test"
	@echo "$(GREEN)** Re-installation completed!!! **$(RESET)"

## Reload DEV and TEST fixtures without recreating databases
.PHONY: reload-fixtures
reload-fixtures:
	@echo "$(YELLOW)** Reload DEV fixtures **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:fixtures:load --no-interaction --group=dev"
	@echo "$(YELLOW)** Reload TEST fixtures **$(RESET)"
	@$(APP) sh -c "bin/console doctrine:fixtures:load -e test --no-interaction --group=test"
	@echo "$(GREEN)** Fixtures reloaded!!! **$(RESET)"

## Execute bin/console dans le container app (Ex: make console c="debug:router")
.PHONY: console $(c)
console:
	@$(APP) sh -c "bin/console ${c}"

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

## Stoppe les containers SANS les détruire (Ex: make stop s=app)
.PHONY: stop $(s)
stop:
	@$(DOCKER) stop ${s}

## Redémarre les containers stoppés (Ex: make start s=app)
.PHONY: start $(s)
start:
	@$(DOCKER) start ${s}

## Redémarre les containers (Ex: make restart s=app)
.PHONY: restart $(s)
restart:
	@$(DOCKER) restart ${s}

## Build les containers
.PHONY: bi
bi:
	@$(DOCKER) build

## Build les containers sans cache
.PHONY: binc
binc:
	@$(DOCKER) build --no-cache

## Connection au ssh du container app
.PHONY: bash-app
bash-app:
	@$(DOCKER) exec app bash

## Connection au ssh du container nginx
.PHONY: bash-nginx
bash-nginx:
	@$(DOCKER) exec nginx sh

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

## Affiche les logs des containers (Ex: make logs s=app)
.PHONY: logs $(s)
logs:
	@$(DOCKER) logs -f ${s}

##--------------------------------- Composer -----------------------------------

## Execute composer
.PHONY: c
c:
	@$(APP) sh -c "composer"

## Execute composer install
.PHONY: ci
ci:
	@$(APP) sh -c "composer install"
	@$(APP) sh -c "find vendor/bin -name '*.bat' -delete"

## Execute composer install
.PHONY: ci-dry
ci-dry:
	@$(APP) sh -c "composer install --dry-run"

## Execute composer update
.PHONY: cu
cu:
	@$(APP) sh -c "composer update"

## Execute composer update dry-run
.PHONY: cu-dry
cu-dry:
	@$(APP) sh -c "composer update --dry-run"

## Execute composer outdated
.PHONY: co
co:
	@$(APP) sh -c "composer outdated"

## Execute composer dump-autoload
.PHONY: cda
cda:
	@$(APP) sh -c "composer dump-autoload"

## Execute composer require
.PHONY: creq $(p)
creq:
	@$(APP) sh -c "composer require ${p}"

## Execute composer require --dev
.PHONY: creqdev $(p)
creqdev:
	@$(APP) sh -c "composer require --dev ${p}"

## Execute composer remove
.PHONY: crem $(p)
crem:
	@$(APP) sh -c "composer remove ${p}"

## Execute composer recipes
.PHONY: cr
cr:
	@$(APP) sh -c "composer recipes"

## List outdated composer recipes
.PHONY: cro
cro:
	@$(APP) sh -c "composer recipes --outdated"

## Install a composer recipe (Ex: make cri p=stripe/stripe-php)
.PHONY: cri
cri:
	@$(APP) sh -c "composer recipes:install ${p}"

## Update a composer recipe (Ex: make cru p=stripe/stripe-php)
.PHONY: cru
cru:
	@$(APP) sh -c "composer recipes:update ${p}"

## Execute composer version
.PHONY: cv
cv:
	@$(APP) sh -c "composer -V"

##--------------------------------- Symfony -----------------------------------

## Affiche les logs applicatifs (php-fpm + workers Messenger)
.PHONY: serve-log
serve-log:
	@$(DOCKER) logs -f app

## Redémarre php-fpm et les workers Messenger (supervisor)
.PHONY: serve-restart
serve-restart:
	@$(APP) sh -c "supervisorctl restart all"

## Consomme les messages du transport async (Messenger)
.PHONY: messenger-consume
messenger-consume:
	@$(APP) sh -c "bin/console messenger:consume async -vv --time-limit=3600 --memory-limit=128M --limit=200 --failure-limit=5"

## Consomme l'outbox des Domain Events (transport domain_events)
.PHONY: messenger-consume-events
messenger-consume-events:
	@$(APP) sh -c "bin/console messenger:consume domain_events -vv --time-limit=3600 --memory-limit=256M --limit=100 --failure-limit=5"

## Demande l'arrêt propre des workers Messenger après leur message en cours
.PHONY: messenger-stop-workers
messenger-stop-workers:
	@$(APP) sh -c "bin/console messenger:stop-workers"

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

## Execute a suite of tests, by setting testsuite name (Ex: make unit-suite s=api.user)
.PHONY: unit-suite $(s)
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

## Purge les dossiers cache and logs
.PHONY: purge
purge:
	@$(APP) sh -c "rm -rf var/cache/ var/log/"

%:
	@:
