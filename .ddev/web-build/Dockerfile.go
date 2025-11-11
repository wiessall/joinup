## Install Go for building publiccode-parser-php binaries
# Version should match GO_VERSION in scripts/composer/build_publiccode_binaries

ARG GO_VERSION=1.25.4
ARG TARGETARCH

RUN apt-get update && apt-get install -y wget \
    && rm -rf /var/lib/apt/lists/*

RUN ARCH=$(dpkg --print-architecture) \
    && if [ "$ARCH" = "arm64" ]; then GO_ARCH="arm64"; \
       elif [ "$ARCH" = "amd64" ]; then GO_ARCH="amd64"; \
       else echo "Unsupported architecture: $ARCH" && exit 1; fi \
    && wget -q https://go.dev/dl/go${GO_VERSION}.linux-${GO_ARCH}.tar.gz -O /tmp/go.tar.gz \
    && tar -C /usr/local -xzf /tmp/go.tar.gz \
    && rm /tmp/go.tar.gz

ENV PATH="/usr/local/go/bin:${PATH}"
ENV GOPATH="/home/.go"
ENV GOCACHE="/home/.cache/go-build"

RUN mkdir -p ${GOPATH} ${GOCACHE} \
    && chmod -R 777 ${GOPATH} ${GOCACHE}
