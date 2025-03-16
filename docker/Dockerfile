FROM ubuntu:22.04

ARG UID=1000
ARG GID=1000

RUN groupadd -g ${GID} appgroup && \
    useradd -m -u ${UID} -g appgroup appuser

WORKDIR /app

ENV DEBIAN_FRONTEND=noninteractive

ENV LANG=en_US.UTF-8
ENV LANGUAGE=en_US:en
ENV LC_ALL=en_US.UTF-8

RUN apt update

RUN apt install -y locales && \
    locale-gen en_US.UTF-8 && \
    update-locale LANG=en_US.UTF-8

RUN apt install -y vim curl make g++
RUN apt install -y python2
RUN apt install -y software-properties-common && \
    add-apt-repository -y ppa:ondrej/php && \
    add-apt-repository -y ppa:ondrej/nginx-mainline && \
    apt install -y mysql-server php8.2

RUN apt install -y curl && \
    curl -sL https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh -o install_nvm.sh && \
    bash install_nvm.sh && \
    export NVM_DIR="$HOME/.nvm" && \
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh" && \
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion" && \
    command -v nvm && \
    bash -c "source $NVM_DIR/nvm.sh && nvm install 22"

RUN apt install -y php8.2-xml php8.2-zip php8.2-gd php8.2-mbstring php8.2-mysql php8.2-curl php8.2-xdebug php8.2-pgsql php8.2-gmp

RUN apt install -y hunspell hunspell-hu

RUN apt install -y sphinxsearch

RUN apt install -y graphicsmagick

RUN apt install -y texlive
RUN apt install -y texlive-xetex
RUN apt install -y texlive-lang-european
RUN apt install -y fonts-linuxlibertine

RUN apt install -y postgresql-common
RUN /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh -y
RUN apt install postgresql-14-pgvector


COPY ../deploy/local/sphinx/sphinx.conf /etc/sphinxsearch
RUN sed -i 's/^START=no/START=yes/' /etc/default/sphinxsearch

CMD ["bash"]
