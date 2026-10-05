.PHONY: assets tests coverage
.DEFAULT_GOAL := help

assets:
	@:

# Load .env (then .env.local: machine-specific overrides, gitignored)
APP_ENV_BAK   := $(APP_ENV)
APP_DEBUG_BAK := $(APP_DEBUG)
ifneq (,$(wildcard .env))
	include .env
endif
ifneq (,$(wildcard .env.local))
	include .env.local
endif
ifneq (,$(wildcard .env.${APP_ENV}))
	include .env.${APP_ENV}
endif
ifneq (,$(wildcard .env.${APP_ENV}.local))
	include .env.${APP_ENV}.local
endif
ifneq ($(APP_ENV_BAK),)
	APP_ENV := $(APP_ENV_BAK)
endif
ifneq ($(APP_DEBUG_BAK),)
	APP_DEBUG := $(APP_DEBUG_BAK)
endif

# Project variables 
PROJECT_DIR	:= $(shell basename "$$(pwd)")
MAKEFILE_DIR   := $(realpath $(dir $(lastword $(MAKEFILE_LIST))))
SOCKFILE	   ?= var/run/nginx.sock
SYMLINK		?= ../$(APP_NAME).sock
APP_REPLICAS   ?= 1
ALLOW_COMPOSER_UPDATE ?= 0
ALLOW_ASSETS_UPDATE ?= 0

# APP_NAME is exported so docker compose interpolation sees it with top
# precedence: compose builds its project env from --env-file files in order,
# so .env's derived vars (MYSQL_HOST=${APP_NAME}-database, DATABASE_URL, …)
# would otherwise be computed before .env.local supplies APP_NAME.
export APP_NAME
export PROJECT_DIR SOCKFILE SYMLINK ALLOW_COMPOSER_UPDATE ALLOW_ASSETS_UPDATE

# Dynamic arguments 
# Usage: make <target> <service> [arg1 arg2 ...]
TARGET  := $(firstword $(MAKECMDGOALS))
SERVICE := $(word 2, $(MAKECMDGOALS))
ARGS	:= $(wordlist 3, $(words $(MAKECMDGOALS)), $(MAKECMDGOALS))

# Verbosity 
ifeq ($(VERBOSE),1)
	VERBOSE_FLAG := -v
else ifeq ($(VERBOSE),2)
	VERBOSE_FLAG := -vv
else ifeq ($(VERBOSE),3)
	VERBOSE_FLAG := -vvv
else
	VERBOSE_FLAG :=
endif

# Colors 
C_RESET   := \033[0m
C_ERROR   := \033[31m
C_OK	  := \033[32m
C_INFO	:= \033[36m
C_WARN	:= \033[33m
C_TITLE   := \033[0;44m\033[37m

# Guards 
NODOCKER  ?= 0
IN_DOCKER := $(shell test -e /.dockerenv && echo yes || echo no)

# Asset build sentinel file
ASSETS_MANIFEST := public/assets/manifest.json

# Docker commands 
ifeq ($(IN_DOCKER),no)
ifneq ($(filter 1 true yes,$(NODOCKER)),)

# NODOCKER mode: run directly
DOCKER		 :=
DOCKER_DEV	 :=
DOCKER_RUN	 :=

else

# .env.local (gitignored, machine-specific) is passed last so its values win
# for ${...} interpolation inside the compose files and env_file contents.
DOCKER		 := docker compose --env-file .env --env-file .env.local -f docker-compose.yml

# In dev: add override
ifeq ($(APP_DEBUG),1)
DOCKER_DEV	 := $(DOCKER) -f docker-compose.override.yml
DOCKER_ADMIN   := $(DOCKER) -f docker-compose.override.yml -f docker-compose.admin.yml
else
DOCKER_DEV	 := $(DOCKER)
DOCKER_ADMIN   := $(DOCKER) -f docker-compose.admin.yml
endif

ifeq ($(APP_DEBUG),1)
DOCKER_SETUP   := $(DOCKER) -f docker-compose.override.yml -f docker-compose.setup.yml -f docker-compose.override.setup.yml
else
DOCKER_SETUP   := $(DOCKER) -f docker-compose.setup.yml
endif

DOCKER_SCALING := $(if $(filter $(SERVICE),),--scale web=$(APP_REPLICAS) --scale worker=$(APP_REPLICAS),)

# Exec shortcuts — resolved at shell time with a clear error if container is not running
# TTY only when we have one (CI and scripted runs don't)
TTY_FLAG       := $(shell [ -t 0 ] && echo -ti || echo -i)
DOCKER_WEB      = docker exec $(TTY_FLAG) $$(docker container ls -f "name=^$(APP_NAME)-web-1$$" -q | grep . || { printf "${C_ERROR}[!] Web container is not running — run: make up${C_RESET}\n" >&2; exit 1; })
DOCKER_DATABASE = docker exec $(TTY_FLAG) $$(docker container ls -f "name=^$(APP_NAME)-database$$" -q | grep . || { printf "${C_ERROR}[!] Database container is not running — run: make up${C_RESET}\n" >&2; exit 1; })
DOCKER_PROXY    = docker exec $(TTY_FLAG) $$(docker container ls -f "name=^$(APP_NAME)-proxy$$" -q    | grep . || { printf "${C_ERROR}[!] Proxy container is not running — run: make up${C_RESET}\n" >&2; exit 1; })

# Composer: always run via setup file (entrypoint handles dev vs prod)
DOCKER_COMPOSER := $(DOCKER_SETUP) run --rm --entrypoint "" composer

# Yarn: in the running assets container ($(APP_NAME)-assets), the one whose
# node_modules the host bind-mounts - never on the host, where yarn would
# rebuild native modules for macOS and break the watcher.
DOCKER_ASSETS   = docker exec $(TTY_FLAG) $$(docker container ls -f "name=^$(APP_NAME)-assets$$" -q | grep . || { printf "${C_ERROR}[!] Assets container is not running — run: make up${C_RESET}\n" >&2; exit 1; })

# Run on all web containers
define DOCKER_WEB_FOREACH
	@set -u; \
	containers="$$(docker ps -q -f name=^$(APP_NAME)-web)"; \
	if [ -z "$$containers" ]; then echo "No web containers running"; exit 0; fi; \
	N=$$(echo "$$containers" | wc -l | tr -d ' '); i=1; \
	for c in $$containers; do \
		ts=$$(date -u '+%Y/%m/%d %H:%M:%S'); \
		printf "%s [web %s] %d/%d: " "$$ts" "$$c" "$$i" "$$N"; \
		output=$$(docker exec -t $$c sh -lc 'set -e; $(1)' 2>&1); rc=$$?; \
		[ $$rc -eq 0 ] && printf "✅\n" || { printf "❌ (exit %s)\n" "$$rc"; echo "$$output"; }; \
		i=$$((i+1)); \
	done
endef

endif
else

# Direct execution inside container
define DOCKER_WEB_FOREACH
	@$(1)
endef

endif

# Create files if missing (.env.local: docker compose --env-file requires it;
# initdb.sql: mounted into the database). The lockfiles are NOT touched: an
# empty composer.lock is invalid JSON, and a fresh checkout has to resolve.
$(shell touch initdb.sql .env.local 2>/dev/null; [ -e yarn.lock ] || echo > yarn.lock; [ -e symfony.lock ] || echo '{}' > symfony.lock)

# ═══════════════════════════════════════════════════════════════
# HELP
# ═══════════════════════════════════════════════════════════════

## Show help
help:
	@printf "${C_TITLE} $(APP_NAME) ${C_RESET}\n\n"
	@printf "${C_WARN}Usage:${C_RESET}\n make [target] [service] [args]\n\n"
	@printf "${C_WARN}Targets:${C_RESET}\n"
	@awk '/^## /{doc=substr($$0,4)} /^[a-zA-Z][a-zA-Z0-9_-]*:/{if(doc){printf " ${C_INFO}%-22s${C_RESET} %s\n",substr($$1,1,index($$1,":")-1),doc}; doc=""}' \
		$(MAKEFILE_LIST)

# ═══════════════════════════════════════════════════════════════
# ENVIRONMENT
# ═══════════════════════════════════════════════════════════════

## Configure environment (dev by default) — writes machine-specific values
## to the gitignored .env.local, never to the committed .env.
env:
	@{ \
	  echo "APP_NAME=$$(basename "$$(pwd)" | tr -d '.')"; \
	  sed -e '/^APP_NAME=/d' -e '/^APP_DEBUG=/d' -e '/^APP_PATH=/d' -e '/^APP_ENV=/d' .env.local 2>/dev/null || true; \
	} > .env.local.tmp && mv .env.local.tmp .env.local
	@echo "APP_PATH=$(MAKEFILE_DIR)" >> .env.local
	@echo "APP_DEBUG=$(APP_DEBUG)" >> .env.local
	@echo "APP_ENV=$(APP_ENV)"   >> .env.local
	@printf "${C_OK}[env]${C_RESET} APP_ENV=$(APP_ENV) APP_DEBUG=$(APP_DEBUG) (written to .env.local)\n"

## Configure environment for development
env-dev:
	@APP_ENV=dev APP_DEBUG=1 $(MAKE) env

## Configure environment for production
env-prod:
	@APP_ENV=prod APP_DEBUG=0 $(MAKE) env

# ═══════════════════════════════════════════════════════════════
# BUILD / SETUP
# ═══════════════════════════════════════════════════════════════

## Guard: check that assets have been built
check-assets:
	@if [ ! -f "$(ASSETS_MANIFEST)" ]; then \
		printf "${C_ERROR}[!] Assets not compiled — run: make build${C_RESET}\n"; \
		exit 1; \
	fi

## Rebuild Docker image(s) (pass SERVICE= to target one, e.g. make image SERVICE=web)
image: env
	@printf "${C_INFO}[image]${C_RESET} Building Docker image(s)...\n"
	@$(DOCKER_DEV) build --pull --provenance=false $(SERVICE)
	@printf "${C_OK}[image]${C_RESET} Done ✅\n"

## Install PHP (composer) and JS (yarn) dependencies + compile assets
build: env
	@$(MAKE) -s build-composer
	@$(MAKE) -s build-assets
	@printf "${C_OK}[build]${C_RESET} Done ✅\n"

## Full build (images + dependencies + assets)
all: image build

## Re-owns files the containers left root-owned on the host bind mounts back
## to the real dev user (composer/yarn/assets all run as root inside the
## images - COMPOSER_ALLOW_SUPERUSER=1 and the builder stage's USER root are
## both deliberate there, so this isn't a container bug to "fix" upstream,
## just fallout from root writing through a host bind that needs mopping up
## after each build). Safe/idempotent to re-run any time host-side tools
## (yarn, git, an IDE, ...) start hitting EACCES on vendor/node_modules/var.
## Usage: make fix-permissions [OWNER=uid:gid]
## Numeric ids, not names: the throwaway chown container has no idea who
## the checkout's owner is (that's a host /etc/passwd entry, not the
## container's), so a name here would just fail to resolve.
OWNER ?= $(shell id -u):$(shell id -g)
fix-permissions:
	@printf "${C_INFO}[fix-permissions]${C_RESET} Re-owning root-written files to $(OWNER)...\n"
	@docker run --rm -v "$(CURDIR)":/mnt alpine chown -R $(OWNER) /mnt
	@printf "${C_OK}[fix-permissions]${C_RESET} Done ✅\n"

## Seeds the public volume ($(APP_NAME)-public) with ./public (index.php...)
## when it exists without an index.php. The composer entrypoint copies ./public
## into the volume only when the volume is EMPTY, and a stack started before
## its first build (`make up`, a bare `docker compose up`) leaves it with the
## public/assets mountpoint alone: not empty, so never seeded, and the site
## answered "File not found". Nothing to do on a volume not created yet (the
## entrypoint seeds it) or already holding index.php.
seed-public:
	@if docker volume inspect $(APP_NAME)-public >/dev/null 2>&1; then \
		docker run --rm -v $(APP_NAME)-public:/to -v "$(CURDIR)/public":/from:ro alpine sh -c \
			'[ -f /to/index.php ] || { echo "[build] public volume without index.php: seeding it from ./public"; find /from -mindepth 1 -maxdepth 1 ! -name assets ! -name bundles -exec cp -a {} /to/ \; ; }'; \
	fi

build-composer:
	@$(MAKE) -s seed-public
	@printf "${C_INFO}[build]${C_RESET} Installing composer...\n"
	@$(DOCKER_SETUP) run --rm -e ALLOW_COMPOSER_UPDATE=$(ALLOW_COMPOSER_UPDATE) composer

build-assets:
	@printf "${C_INFO}[build]${C_RESET} Compiling assets...\n"
	@# The assets service bind-mounts . read-only and overlays an anonymous
	@# volume at /srv/app/node_modules — on a fresh clone the mountpoint dir
	@# doesn't exist yet and docker can't create it inside the ro rootfs.
	@mkdir -p node_modules
	@$(DOCKER_SETUP) run --rm -e ALLOW_ASSETS_UPDATE=$(ALLOW_ASSETS_UPDATE) assets

## Sync the runtime vendor volume ($(APP_NAME)-vendor) back to host ./vendor (keeps IDE in sync with containers)
vendor-sync:
	@printf "${C_INFO}[vendor]${C_RESET} Syncing $(APP_NAME)-vendor volume → ./vendor ...\n"
	@docker volume inspect $(APP_NAME)-vendor >/dev/null 2>&1 || { printf "${C_ERROR}[!] Volume $(APP_NAME)-vendor not found — run: make build${C_RESET}\n"; exit 1; }
	@docker run --rm \
		-v $(APP_NAME)-vendor:/from:ro \
		-v "$(CURDIR)/vendor":/to \
		$(APP_NAME)-web \
		rsync -a --delete --exclude='.git/' /from/ /to/
	@printf "${C_OK}[vendor]${C_RESET} ./vendor is in sync ✅\n"

## Rebuild a single vendor's assets (usage: make build-vendor glitchr/omnibase)
build-vendor:
	@if [ -z "$(SERVICE)" ]; then \
		printf "${C_ERROR}[!] Usage: make build-vendor <vendor/name>${C_RESET}\n"; \
		printf "    Example: make build-vendor glitchr/omnibase\n"; \
		exit 1; \
	fi
	@if [ ! -d "vendor/$(SERVICE)" ]; then \
		printf "${C_ERROR}[!] Vendor directory not found: vendor/$(SERVICE)${C_RESET}\n"; \
		exit 1; \
	fi
	@if [ ! -f "vendor/$(SERVICE)/Makefile" ]; then \
		printf "${C_ERROR}[!] No Makefile in vendor/$(SERVICE)${C_RESET}\n"; \
		exit 1; \
	fi
	@printf "${C_INFO}[build-vendor]${C_RESET} Compiling assets for vendor/$(SERVICE)...\n"
	@$(DOCKER_SETUP) run --rm --no-deps --entrypoint sh assets -lc 'cd vendor/$(SERVICE) && APP_DEBUG=$(APP_DEBUG) APP_ENV=$(APP_ENV) make assets'
	@printf "${C_OK}[build-vendor]${C_RESET} Done ✅\n"

## Full install + startup (first run)
install: build up

## Full install in development mode
install-dev: env-dev build
	@$(MAKE) up

# ═══════════════════════════════════════════════════════════════
# LIFECYCLE
# ═══════════════════════════════════════════════════════════════

## Start main services
# Bring the database up BEFORE the services that depend on it.
#
# On a first boot the mysql image initialises, then exits 0 - cleanly, at
# "Creating database" - and restart:unless-stopped starts it again 400ms
# later, after which it is healthy. Measured: 26s alone, 50s inside the full
# stack, exit code 0, OOMKilled false, RestartCount 1.
#
# `docker compose up` does not wait through that. A dependency that STOPS,
# even to come straight back, aborts the whole up with "dependency failed to
# start: container <x>-database is unhealthy" - which reads like a sick
# database and is really a healthy one caught mid-restart. It only bites a
# fresh volume, so the deploy gate (new volume every run) hit it every time
# while beta and production, whose volumes are long initialised, never did.
#
# Starting it first lets that restart happen with nothing waiting on it.
# --wait is bounded by the healthcheck's own start_period/retries, and doing
# this for an already-initialised volume costs one extra healthy check.
DB_FIRST = database

up: env
ifeq ($(APP_DEBUG),1)
	@docker volume inspect $(APP_NAME)-vendor >/dev/null 2>&1 || \
		printf "${C_WARN}[!] Vendor volume not found — run: make build${C_RESET}\n"
	@if [ -z "$(SERVICE)" ]; then $(DOCKER_DEV) up -d --wait $(DB_FIRST) >/dev/null 2>&1 || true; fi
	@$(DOCKER_DEV) up -d $(DOCKER_SCALING) --remove-orphans $(SERVICE)
	@printf "${C_INFO}[up]${C_RESET} Sources mounted from ${C_INFO}local directory${C_RESET} (assets in ${C_INFO}watch${C_RESET} mode)\n"
else
	@docker volume inspect $(APP_NAME)-vendor >/dev/null 2>&1 || \
		printf "${C_WARN}[!] Vendor volume not found — run: make build${C_RESET}\n"
	@if [ -z "$(SERVICE)" ]; then $(DOCKER) up -d --wait $(DB_FIRST) >/dev/null 2>&1 || true; fi
	@$(DOCKER) up -d $(DOCKER_SCALING) --remove-orphans $(SERVICE)
	@printf "${C_INFO}[up]${C_RESET} Sources loaded from ${C_OK}Docker volume${C_RESET}\n"
endif

## Stop services (and admin services if active)
down:
	@$(DOCKER_ADMIN) down --remove-orphans $(SERVICE) || \
	 $(DOCKER_DEV)   down --remove-orphans $(SERVICE)

## Restart services
restart:
	@$(MAKE) down $(SERVICE)
	@$(MAKE) up   $(SERVICE)

## Follow logs in real time
##
## Each container's log lines get a colored "[<name>]" prefix so a multi-
## container tail is scannable. The color is derived from a checksum of
## the container name (stable across runs — `queue` is always the same
## color, `web` always the same, etc.) and cycles through 12 ANSI codes
## (31-36 normal, 91-96 bright) — enough distinct hues that adjacent
## containers don't collide visually in typical 6-8 container projects.
N := 0
logs:
	@filter="$(SERVICE)"; \
	pattern=$$([ -n "$$filter" ] && echo "^[^ ]+ $(APP_NAME)-$$filter" || echo "^[^ ]+ $(APP_NAME)-"); \
	containers="$$(docker ps --format '{{.ID}} {{.Names}}' | grep -E "$$pattern" | awk '{print $$1}' || true)"; \
	[ -z "$$containers" ] && $(DOCKER_DEV) logs $(if $(N),-n $(N)) $$filter && exit 0; \
	pids=""; \
	colors="31 32 33 34 35 36 91 92 93 94 95 96"; \
	for id in $$containers; do \
		name=$$(docker inspect --format '{{.Name}}' $$id | sed 's|/||'); \
		hash=$$(printf '%s' "$$name" | cksum | cut -d' ' -f1); \
		idx=$$(( ($$hash % 12) + 1 )); \
		color=$$(echo $$colors | cut -d' ' -f$$idx); \
		docker logs -f $(if $(N),-n $(N)) $$id 2>&1 | awk -v n="$$name" -v c="$$color" 'BEGIN{prefix=sprintf("\033[1;%sm[%s]\033[0m", c, n)} {print prefix " " $$0}' & \
		pids="$$pids $$!"; \
	done; \
	trap 'kill $$pids 2>/dev/null; exit 0' INT TERM; \
	wait

## Full update
update:
	@$(MAKE) build ALLOW_COMPOSER_UPDATE=1 ALLOW_ASSETS_UPDATE=1
ifneq ($(APP_DEBUG),1)
	@$(MAKE) -s vendor-sync
endif

## Enable admin services (phpMyAdmin, Redis consoles)
admin:
	@$(DOCKER_ADMIN) up -d --remove-orphans
	@printf "${C_WARN}[admin]${C_RESET} Admin services started\n"
	@printf "${C_WARN}[admin]${C_RESET} Access via internal proxy only\n"

## Disable admin services
admin-stop:
	@$(DOCKER_ADMIN) down --remove-orphans
	@printf "${C_OK}[admin]${C_RESET} Admin services stopped\n"

# ═══════════════════════════════════════════════════════════════
# INTROSPECTION
# ═══════════════════════════════════════════════════════════════

## List containers with CPU, RAM and ports
ps:
	@printf "${C_TITLE} Containers $(APP_NAME) ${C_RESET}\n\n"
	@printf "${C_WARN}%-30s %-16s %7s %11s  %s${C_RESET}\n" "Service" "Status" "CPU" "Memory" "Ports"
	@printf "${C_WARN}%.s${C_RESET}" $$(seq 80); printf "\n"
	@stats="$$(docker stats --no-stream --format '{{.ID}} {{.CPUPerc}} {{.MemUsage}}')"; \
	docker ps -a --filter "name=^$(APP_NAME)-" --format '{{.ID}} {{.Names}} {{.Status}}' | \
	while read -r id name raw_status; do \
		line="$$(echo "$$stats" | awk -v id="$$id" '$$1==id')"; \
		cpu="$$(echo "$$line" | awk '{print $$2}')"; \
		mem="$$(echo "$$line" | awk '{print $$3}')"; \
		[ -z "$$cpu" ] && cpu="—"; [ -z "$$mem" ] && mem="—"; \
		ports="$$(docker port $$id 2>/dev/null | awk -F'[/: ]' '/0\.0\.0\.0/{printf "%s→%s ", $$1, $$NF}' | sed 's/ $$//')"; \
		[ -z "$$ports" ] && ports="—"; \
		svc_name=$$(echo "$$name" | sed "s/^$(APP_NAME)-//"); \
		case "$$raw_status" in \
			*healthy*)   status_color="${C_OK}";; \
			Up*)         status_color="${C_INFO}";; \
			*)           status_color="${C_ERROR}";; \
		esac; \
		short_status=$$(echo "$$raw_status" | sed 's/Up /↑ /; s/Exited ([0-9]*)/↓ Exited/; s/ (healthy)//; s/ (unhealthy)/ !/; s/ ago//'); \
		printf "${C_INFO}%-30s${C_RESET} %b%-16s%b %7s %11s  ${C_WARN}%s${C_RESET}\n" \
			"$$svc_name" "$$status_color" "$$short_status" "${C_RESET}" "$$cpu" "$$mem" "$$ports"; \
	done
	@printf "\n"


## List project volumes with size and mount point
volumes:
	@printf "${C_TITLE} Volumes $(APP_NAME) ${C_RESET}\n"
	@for vol in $$(docker volume ls --format '{{.Name}}' | grep '^$(APP_NAME)-'); do \
		size="$$(docker system df -v --format '{{json .}}' | \
			jq -r --arg v "$$vol" '.Volumes[]|select(.Name==$$v)|.Size // "N/A"')"; \
		mount="$$(docker volume inspect -f '{{.Mountpoint}}' $$vol 2>/dev/null || echo '—')"; \
		printf "%-40s %-10s %-60s\n" "$$vol" "$$size" "$$mount"; \
	done

## List project networks
networks:
	@printf "${C_TITLE} Networks $(APP_NAME) ${C_RESET}\n"
	@docker network ls --format '{{.Name}} {{.Driver}} {{.Scope}}' | grep "^$(APP_NAME)" | \
	while read -r name driver scope; do \
		count="$$(docker network inspect -f '{{len .Containers}}' $$name)"; \
		printf "%-40s %-10s %-10s %s containers\n" "$$name" "$$driver" "$$scope" "$$count"; \
	done

## Open the application in the browser
open:
	@PROXY="$(APP_NAME)-proxy"; \
	HTTPS="$$(docker inspect --format='{{range $$p,$$c := .NetworkSettings.Ports}}{{if eq $$p "443/tcp"}}{{(index $$c 0).HostPort}}{{end}}{{end}}' $$PROXY 2>/dev/null)"; \
	HTTP="$$(docker inspect --format='{{range $$p,$$c := .NetworkSettings.Ports}}{{if eq $$p "80/tcp"}}{{(index $$c 0).HostPort}}{{end}}{{end}}' $$PROXY 2>/dev/null)"; \
	URL=$$([ -n "$$HTTPS" ] && echo "https://localhost:$$HTTPS" || \
		   [ -n "$$HTTP"  ] && echo "http://localhost:$$HTTP"   || \
		   echo "https://localhost:${APP_HTTPS:-443}"); \
	echo "Opening $$URL"; \
	command -v open >/dev/null && open "$$URL" || \
	command -v xdg-open >/dev/null && xdg-open "$$URL" || \
	echo "URL: $$URL"

## Create or repair the nginx.sock symlink
socket:
	@[ -L "$(SYMLINK)" ] && [ -e "$(SYMLINK)" ] && exit 0; \
	rm -f "$(SYMLINK)"; \
	ln -s "$(PROJECT_DIR)/$(SOCKFILE)" "$(SYMLINK)"; \
	echo "Socket → $(SYMLINK)"

# ═══════════════════════════════════════════════════════════════
# SHELL / CONSOLE
# ═══════════════════════════════════════════════════════════════

## Open a shell in a container (default: web-1)
shell:
	@svc=$$([ -n "$(SERVICE)" ] && echo "$(SERVICE)" || echo "web-1"); \
	cid=$$(docker container ls -f "name=^$(APP_NAME)-$$svc$$" -q); \
	[ -z "$$cid" ] && echo "Container '$$svc' not found" && exit 1; \
	[ -n "$(ARGS)" ] && docker exec $(TTY_FLAG) $$cid sh -c '$(ARGS)' || docker exec -ti $$cid bash

## Drop into a shell inside a container for debugging (bypasses depends_on, dynamic like shell)
shell-debug: env
	@svc=$$([ -n "$(SERVICE)" ] && echo "$(SERVICE)" || echo "assets"); \
	cid=$$(docker container ls -f "name=^$(APP_NAME)-$$svc$$" -q); \
	if [ -n "$$cid" ]; then \
		printf "${C_INFO}[shell-debug]${C_RESET} Opening shell in container '$$svc'...\n"; \
		[ -n "$(ARGS)" ] && docker exec $(TTY_FLAG) $$cid sh -c '$(ARGS)' || docker exec -ti $$cid sh; \
	else \
		printf "${C_INFO}[shell-debug]${C_RESET} Starting new debug container for '$$svc'...\n"; \
		[ -n "$(ARGS)" ] && $(DOCKER_SETUP) run --rm --no-deps --entrypoint sh $$svc -c '$(ARGS)' || $(DOCKER_SETUP) run --rm --no-deps --entrypoint sh $$svc; \
	fi

## Open a console (database or bin/console symfony)
console:
ifeq ($(SERVICE),database)
	@$(DOCKER_DATABASE) mysql -h localhost -u root -p$(MYSQL_ROOT_PASSWORD) $(MYSQL_DATABASE)
else
	@$(DOCKER_WEB) php bin/console $(SERVICE) $(ARGS)
endif

# ═══════════════════════════════════════════════════════════════
# DEPENDENCIES
# ═══════════════════════════════════════════════════════════════

## Yarn in the assets container (usage: make yarn ARGS="upgrade @glitchr/stickyjs")
yarn:
	@$(DOCKER_ASSETS) yarn $(ARGS)

## Composer help
composer-help:
	@$(DOCKER_COMPOSER) composer help

## Re-run composer post-install scripts
composer-post-install:
	@$(DOCKER_COMPOSER) composer run-script post-install-cmd

## Optimize composer autoload
composer-autoload:
	@$(DOCKER_COMPOSER) composer dump-autoload

# ═══════════════════════════════════════════════════════════════
# DATABASE
# ═══════════════════════════════════════════════════════════════

## Export database to initdb.sql
database-export:
	@printf "${C_INFO}[db]${C_RESET} Exporting...\n"
	@$(DOCKER_DATABASE) sh -c \
		'mysqldump -h $(MYSQL_HOST) -u $(MYSQL_USER) -p"$(MYSQL_PASSWORD)" $(MYSQL_DATABASE) \
		 > /docker-entrypoint-initdb.d/initdb.sql'
	@printf "${C_OK}[db]${C_RESET} Export complete → initdb.sql\n"

## Write the beta hosts' basic-auth file (.htpasswd, gitignored). Usage: make htpasswd [BETA_USER=admin] [BETA_PASSWORD=...]
BETA_USER ?= admin
htpasswd:
	@pass="$${BETA_PASSWORD:-$$(openssl rand -base64 18)}"; \
	printf '%s:%s\n' "$(BETA_USER)" "$$(openssl passwd -apr1 "$$pass")" > .htpasswd; \
	printf "${C_OK}[htpasswd]${C_RESET} $(BETA_USER) / %s written to .htpasswd\n" "$$pass"

## Create the test database (<database>_test, see when@test in doctrine.yaml) and migrate it
database-test:
	@$(DOCKER_DATABASE) mysql -h localhost -u root -p$(MYSQL_ROOT_PASSWORD) -e "CREATE DATABASE IF NOT EXISTS $(MYSQL_DATABASE)_test; GRANT ALL ON $(MYSQL_DATABASE)_test.* TO '$(MYSQL_USER)'@'%'; FLUSH PRIVILEGES;"
	@$(DOCKER_WEB) php bin/console doctrine:migrations:migrate --env=test --no-interaction
	@printf "${C_OK}[db]${C_RESET} $(MYSQL_DATABASE)_test ready\n"

## Delete database volume (irreversible)
database-drop:
	@read -p "⚠️  Delete the database? (yes/no) " c; [ "$$c" = "yes" ] || exit 0; \
	$(DOCKER_DEV) rm -fsv database; \
	docker volume rm $(APP_NAME)-database; \
	echo "Database volume deleted."

## Show Doctrine migrations status
doctrine:
	@$(DOCKER_WEB) php bin/console doctrine:migrations:list

## Update schema without migration (dev only)
doctrine-update:
	@$(DOCKER_WEB) php bin/console doctrine:schema:update --dump-sql --force $(VERBOSE_FLAG)

## Prepare a migration
doctrine-migration:
	@$(DOCKER_WEB) php bin/console make:migration $(VERBOSE_FLAG)

## Run migrations
doctrine-migrate:
	@$(DOCKER_WEB) php bin/console doctrine:migrations:migrate $(VERBOSE_FLAG)

## Load fixtures
doctrine-fixtures:
ifeq ($(NON_INTERACTIVE),1)
	@$(call DOCKER_WEB_FOREACH, php bin/console doctrine:fixtures:load $(VERBOSE_FLAG) -n)
else
	@$(call DOCKER_WEB_FOREACH, php bin/console doctrine:fixtures:load $(VERBOSE_FLAG))
endif
	@$(MAKE) sessions

# ═══════════════════════════════════════════════════════════════
# STORAGE / VOLUMES
# ═══════════════════════════════════════════════════════════════

## Import local storage → Docker volume
storage:
	@$(DOCKER_WEB) rsync -a /mnt/app/var/storage/ /srv/app/var/storage/ $(VERBOSE_FLAG)

## Export storage volume → local
storage-export:
	@$(DOCKER_WEB) rsync -a --delete /srv/app/var/storage/ /mnt/app/var/storage/ $(VERBOSE_FLAG)

## Delete storage volume (irreversible)
storage-drop:
	@read -p "⚠️  Delete storage? (yes/no) " c; [ "$$c" = "yes" ] || exit 0; \
	$(DOCKER_DEV) rm -fsv storage; \
	docker volume rm $(APP_NAME)-storage; \
	echo "Storage volume deleted."

## Delete mailer volume (irreversible)
mail-drop:
	@read -p "⚠️  Delete mailer? (yes/no) " c; [ "$$c" = "yes" ] || exit 0; \
	$(DOCKER_DEV) rm -fsv mailer; \
	docker volume rm $(APP_NAME)-mailer; \
	echo "Mailer volume deleted."

## Delete vendor volume (irreversible)
vendor-drop:
	@read -p "⚠️  Delete vendor? (yes/no) " c; [ "$$c" = "yes" ] || exit 0; \
	docker volume rm $(APP_NAME)-vendor; \
	echo "Vendor volume deleted."

# ═══════════════════════════════════════════════════════════════
# CACHE / PROXY
# ═══════════════════════════════════════════════════════════════

## HTTP-layer cache — reload nginx config and flush its proxy cache at var/cache/nginx/edge. Use after editing nginx conf or to force-evict a stale cached response. Touches no PHP, no Symfony, no workers.
edge:
ifeq ($(IN_DOCKER),no)
	@$(DOCKER_PROXY) nginx -s reload 2>/dev/null || true
	@$(call DOCKER_WEB_FOREACH, nginx -s reload && rm -rf /var/cache/nginx/edge)
else
	@nginx -s reload 2>/dev/null || true
	@rm -rf /var/cache/nginx/edge
endif

## Warm the application cache from the CLI, BEFORE a browser gets to it. In
## dev, a request that finds the container/routes/pools stale rebuilds them
## itself, and two requests doing that at once can boot from a half-built
## cache (seen live as a "class not found" for a repository alias). Run this
## after changing config/, vendor/, composer.lock or a bundle checkout;
## the beta checkout's post-merge/post-checkout git hooks call it.
warm:
ifeq ($(IN_DOCKER),no)
	@$(call DOCKER_WEB_FOREACH, php bin/console cache:warmup $(VERBOSE_FLAG))
else
	@php bin/console cache:warmup $(VERBOSE_FLAG)
endif

## Application-layer cache — rebuild Symfony's compiled cache in var/cache (DI container, route table, twig, annotations) via bin/console cache:clear. Use after editing services.yaml, routes, bundle config, or anything in config/. Slow (seconds), no worker restart.
cache:
ifeq ($(IN_DOCKER),no)
	@$(call DOCKER_WEB_FOREACH, php bin/console cache:clear $(VERBOSE_FLAG))
else
	@php bin/console cache:clear $(VERBOSE_FLAG)
endif

## PHP-bytecode cache — reset the in-memory OPcache on every web container and restart queue workers so they pick up new PHP source. Use after a code-only deploy where no config/routes/services changed. Fast (sub-second per container).
opcache:
ifeq ($(IN_DOCKER),no)
	@$(call DOCKER_WEB_FOREACH, curl -sf http://127.0.0.1/admin/_/opcache/clear || true)
	@$(MAKE) restart worker
else
	@curl -sf http://127.0.0.1/admin/_/opcache/clear || true
endif

## Two-stage clear — runs edge then cache. Equivalent to: make edge && make cache.
##
## The OPcache reset step is NOT bundled here because cache:clear already calls
## \opcache_reset() internally via CacheClearTrait::clearOPCache() — that
## handles the CLI process's OPcache (and is a no-op in dev where
## PHP_OPCACHE=0). The HTTP-triggered web SAPI reset + queue worker restart
## live in the separate `make opcache` target for explicit invocation after
## a deploy or when PHP-FPM workers need to drop stale bytecode.
clear: edge cache

## Start proxy if needed, reload nginx, show URL
proxy:
ifeq ($(IN_DOCKER),no)
	@cid=$$(docker container ls -f "name=^$(APP_NAME)-proxy$$" -q); \
	if [ -z "$$cid" ]; then \
		printf "${C_INFO}[proxy]${C_RESET} Proxy not running — starting...\n"; \
		$(DOCKER_DEV) up -d --no-deps proxy; \
	else \
		$(DOCKER_PROXY) nginx -s reload 2>/dev/null || true; \
	fi
	@$(call DOCKER_WEB_FOREACH, nginx -s reload 2>/dev/null || true; php bin/console cache:clear $(VERBOSE_FLAG))
	@PROXY="$(APP_NAME)-proxy"; \
	HTTPS="$$(docker inspect --format='{{range $$p,$$c := .NetworkSettings.Ports}}{{if eq $$p "443/tcp"}}{{(index $$c 0).HostPort}}{{end}}{{end}}' $$PROXY 2>/dev/null)"; \
	HTTP="$$(docker inspect --format='{{range $$p,$$c := .NetworkSettings.Ports}}{{if eq $$p "80/tcp"}}{{(index $$c 0).HostPort}}{{end}}{{end}}' $$PROXY 2>/dev/null)"; \
	[ -n "$$HTTPS" ] && printf "${C_OK}[proxy]${C_RESET} https://localhost:$$HTTPS\n" || true; \
	[ -n "$$HTTP"  ] && printf "${C_OK}[proxy]${C_RESET} http://localhost:$$HTTP\n"  || true
else
	@nginx -s reload 2>/dev/null || true
endif

## Clear symfony and opcache caches
kill:
ifeq ($(IN_DOCKER),no)
	@$(call DOCKER_WEB_FOREACH, rm -rf var/cache/* var/session/* && composer clear-cache)
else
	@rm -rf var/cache/* var/session/* && composer clear-cache
endif

## Clear application sessions
sessions:
	@$(call DOCKER_WEB_FOREACH, php bin/console cache:clear:sessions $(VERBOSE_FLAG))

# ═══════════════════════════════════════════════════════════════
# SEARCH
# ═══════════════════════════════════════════════════════════════

## Start message consumer
messenger:
	@$(DOCKER_WEB) php bin/console messenger:consume async

## Show or replay failed messages (ID=xxx to replay)
messenger-fail:
	@if [ -n "$(ID)" ]; then \
		$(DOCKER_WEB) php bin/console messenger:failed:retry $(ID) --transport=failed; \
	else \
		$(DOCKER_WEB) php bin/console messenger:failed:show; \
	fi

## Replay every failed message from the web container (which has extranet
## access, unlike worker/cron) — use after an outage stranded messages in
## the failed transport, e.g. worker/cron temporarily lacking egress.
messenger-retry-failed:
	@$(DOCKER_WEB) php bin/console messenger:failed:retry --transport=failed --force

# ═══════════════════════════════════════════════════════════════
# APP UTILITIES
# ═══════════════════════════════════════════════════════════════

## Take a timemachine snapshot (usage: make backup [storage], default local.backup)
backup:
	@$(DOCKER_WEB) php bin/console timemachine:snapshot:backup $(or $(SERVICE),local.backup) -vv --batch --database default

## Show file counts + sizes in the LIVE local storage (var/storage/uploads, var/storage/wysiwyg) — what the app actually serves from when MEDIA_*_STORAGE=local.*
storage-ls:
	@$(DOCKER_WEB) sh -c '\
		echo "--- local.uploads (var/storage/uploads) ---"; \
		find var/storage/uploads -type f 2>/dev/null | wc -l | xargs echo "files:"; \
		du -sh var/storage/uploads 2>/dev/null; \
		echo; \
		echo "--- local.wysiwyg (var/storage/wysiwyg) ---"; \
		find var/storage/wysiwyg -type f 2>/dev/null | wc -l | xargs echo "files:"; \
		du -sh var/storage/wysiwyg 2>/dev/null \
	'

## Send a test email — no args: notifier test to the technical recipient; with emails: raw send to each (usage: make mail-test [email1 email2 ...])
mail-test:
	@if [ -z "$(SERVICE)" ]; then \
		$(DOCKER_WEB) php bin/console notifier:mail:test $(VERBOSE_FLAG); \
	else \
		for email in $(SERVICE) $(ARGS); do \
			printf "${C_INFO}[mail-test]${C_RESET} sending to %s...\n" "$$email"; \
			if $(DOCKER_WEB) php bin/console mailer:test "$$email" --from=$(MAILER_TECHNICAL) --subject="Test email" --body="Test email from make mail-test." $(VERBOSE_FLAG); then \
				printf "${C_OK}[mail-test]${C_RESET} sent to %s ✅\n" "$$email"; \
			else \
				printf "${C_ERROR}[mail-test]${C_RESET} FAILED for %s ❌\n" "$$email"; \
			fi; \
		done; \
	fi

## Publish publishable threads
publishable:
	@$(DOCKER_WEB) php bin/console thread:publishable --publish

## Clean up orphaned uploaded entities
orphans-cleanup:
	@$(DOCKER_WEB) php bin/console uploader:entities -v --delete-orphans

## List application secrets
secrets:
	@$(DOCKER_WEB) php bin/console secrets:list --reveal

## Test a route (usage: make route /my/path)
route:
	@$(DOCKER_WEB) php bin/console router:match $(SERVICE)

## List all routes
router:
	@$(DOCKER_WEB) php bin/console debug:router

## List listeners and subscribers
dispatcher:
	@$(DOCKER_WEB) php bin/console debug:event-dispatcher

## List API resources
api:
	@$(call DOCKER_WEB_FOREACH, php bin/console debug:api-resource "$(SERVICE)")

# ═══════════════════════════════════════════════════════════════
# CODE QUALITY (QA)
# ═══════════════════════════════════════════════════════════════

QA_IMAGE   := jakzal/phpqa
QA_RUN	 := docker run --init -it --rm -v $(PWD):/srv/local -w /srv/local $(QA_IMAGE)
QA_SRC	 := ./src

## Fix code style (PHP-CS-Fixer)
cs-fixer:
	@$(QA_RUN) php-cs-fixer fix $(QA_SRC) --rules=@Symfony

## Check style without modifying (dry-run)
cs-fixer-dry:
	@$(QA_RUN) php-cs-fixer fix $(QA_SRC) --rules=@Symfony --dry-run

## Static analysis PHPStan (level 5)
phpstan:
	@$(QA_RUN) phpstan analyse $(QA_SRC) --level=5

## Detect duplicated code (phpcpd)
phpcpd:
	@$(QA_RUN) phpcpd $(QA_SRC)

## Run the full QA suite
qa: cs-fixer-dry phpstan phpcpd

# ═══════════════════════════════════════════════════════════════
# TESTS
# ═══════════════════════════════════════════════════════════════

# PHPUnit runs inside the web container (APP_ENV=test forced by
# phpunit.xml.dist) so the kernel reaches the compose-internal database and
# maildev services. MAILER_LOOPBACK stays ON in .env.test and when@test pins
# the mailer DSN to maildev — no test can ever send a real email.
#
# Usage:
#   make tests             	# every suite
#   make tests app         	# application suite only
#   make tests coverage    	# full run + HTML/text coverage (Xdebug)
## Run PHPUnit suites (usage: make tests [app|coverage])
tests:
ifeq ($(SERVICE),coverage)
	@printf "${C_INFO}[tests]${C_RESET} Running full suite with coverage (Xdebug)...\n"
	@$(DOCKER_WEB) sh -c 'XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html var/coverage --coverage-text $(ARGS)'
	@printf "${C_OK}[tests]${C_RESET} Coverage report → var/coverage/index.html ✅\n"
else ifneq ($(SERVICE),)
	@$(DOCKER_WEB) php vendor/bin/phpunit --testsuite $(SERVICE) $(ARGS)
else
	@$(DOCKER_WEB) php vendor/bin/phpunit
endif

## Alias: make coverage == make tests coverage
coverage:
ifeq ($(TARGET),coverage)
	@$(MAKE) tests coverage
else
	@: # no-op when "coverage" is an argument of another target (make tests coverage)
endif

# Catch-all to avoid "No rule to make target" 
%:
	@:

## Print the Stripe CLI's webhook signing secret (dev): the value of STRIPE_WEBHOOK_SECRET
stripe-secret:
	@docker exec $(APP_NAME)-stripe sh -c 'stripe listen --device-name $(APP_NAME)-sandbox --print-secret'
