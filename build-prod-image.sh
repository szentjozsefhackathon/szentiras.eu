DIR=dist
 echo Copy Sphinx config do $DIR
 mkdir -p $DIR/deploy/production
 cp -R deploy/production/sphinx $DIR/deploy/production

FILE_PATH=$DIR/szeu-app-prod.tar.gz
 echo $FILE_PATH - Creating  docker image for distribution. 
 echo

 APP_DOMAIN=szentiras.eu GIT_COMMIT_HASH=$(git rev-parse HEAD) docker compose -f docker-compose.prod.yml build app
 docker save szeu-app-prod:latest | gzip > $FILE_PATH