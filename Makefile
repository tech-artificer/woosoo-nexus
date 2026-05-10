.PHONY: help setup setup-docker setup-native deploy test clean logs report refresh stop

help:
	@echo "Krypton ↔ Woosoo BI Platform - Makefile"
	@echo "========================================"
	@echo ""
	@echo "Setup Commands:"
	@echo "  make setup              Setup with Docker (default)"
	@echo "  make setup-docker       Setup with Docker"
	@echo "  make setup-native       Setup with native Python"
	@echo ""
	@echo "Operation Commands:"
	@echo "  make deploy             Deploy SQL views to database"
	@echo "  make refresh            Run ETL refresh (generate report)"
	@echo "  make report             Generate drift report"
	@echo "  make test               Test database connection"
	@echo ""
	@echo "Maintenance Commands:"
	@echo "  make logs               Follow ETL logs (Docker)"
	@echo "  make stop               Stop all Docker services"
	@echo "  make clean              Remove Docker volumes & local logs"
	@echo ""
	@echo "Configuration:"
	@echo "  Edit .env to customize database connection and refresh schedule"
	@echo ""

setup: setup-docker

setup-docker:
	@echo "Setting up with Docker..."
	bash quickstart.sh docker

setup-native:
	@echo "Setting up native installation..."
	bash quickstart.sh

deploy:
	@if [ -f .env ]; then \
		. .env; \
		if [ "$$DB_DIALECT" = "mysql" ]; then \
			echo "Deploying to MySQL $$DB_HOST..."; \
			mysql -h $$DB_HOST -u $$DB_USER -p$$DB_PASSWORD $$DB_NAME < krypton_woosoo_bi_views.sql; \
		else \
			echo "Deploying to PostgreSQL $$DB_HOST..."; \
			PGPASSWORD=$$DB_PASSWORD psql -h $$DB_HOST -U $$DB_USER -d $$DB_NAME -f krypton_woosoo_bi_views.sql; \
		fi; \
		echo "✓ Deployment complete"; \
	else \
		echo "ERROR: .env not found. Run 'make setup' first."; \
		exit 1; \
	fi

test:
	@if [ -f .env ]; then \
		. .env; \
		echo "Testing $$DB_DIALECT connection to $$DB_HOST..."; \
		python3 bi_processor.py \
			--dialect $$DB_DIALECT \
			--host $$DB_HOST \
			--user $$DB_USER \
			--password $$DB_PASSWORD \
			--database $$DB_NAME \
			--order-id 1; \
	else \
		echo "ERROR: .env not found. Run 'make setup' first."; \
		exit 1; \
	fi

refresh:
	@if [ -f .env ]; then \
		. .env; \
		echo "Running ETL refresh..."; \
		mkdir -p reports logs; \
		python3 bi_processor.py \
			--dialect $$DB_DIALECT \
			--host $$DB_HOST \
			--user $$DB_USER \
			--password $$DB_PASSWORD \
			--database $$DB_NAME \
			--refresh \
			--report \
			--output reports/drift_report_$$(date +%Y%m%d_%H%M%S).json; \
		echo "✓ Report generated in reports/"; \
	else \
		echo "ERROR: .env not found. Run 'make setup' first."; \
		exit 1; \
	fi

report:
	@if [ -f .env ]; then \
		. .env; \
		echo "Generating drift report..."; \
		mkdir -p reports; \
		python3 bi_processor.py \
			--dialect $$DB_DIALECT \
			--host $$DB_HOST \
			--user $$DB_USER \
			--password $$DB_PASSWORD \
			--database $$DB_NAME \
			--report \
			--output reports/drift_report_$$(date +%Y%m%d_%H%M%S).json; \
	else \
		echo "ERROR: .env not found. Run 'make setup' first."; \
		exit 1; \
	fi

logs:
	@echo "Following ETL logs..."
	docker-compose logs -f bi-processor

stop:
	@echo "Stopping Docker services..."
	docker-compose down

clean:
	@echo "Cleaning up Docker volumes and local logs..."
	docker-compose down -v
	rm -rf logs/ reports/
	echo "✓ Cleanup complete"

.PHONY: help setup setup-docker setup-native deploy test refresh report logs stop clean
