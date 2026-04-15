# syntax=docker/dockerfile:1

FROM golang:1.22-bookworm AS build
WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download
COPY src ./src
COPY migrations ./migrations
RUN CGO_ENABLED=0 go build -o /out/server ./src/cmd/server

FROM debian:bookworm-slim
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates wget \
	&& rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY --from=build /out/server /app/server
COPY migrations /app/migrations
ENV MIGRATIONS_DIR=/app/migrations
EXPOSE 8080
ENTRYPOINT ["/app/server"]
