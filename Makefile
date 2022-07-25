.PHONY: all
all: backend

.PHONY: backend
backend:
	cd backend && php .build/build.php
