FILE_PATH=dist/szeu-app-prod.tar.gz
 echo $FILE_PATH - Creating  docker image for distribution. 
 echo
 APP_DOMAIN=szentiras.eu GIT_COMMIT_HASH=$(git rev-parse HEAD) docker compose -f docker-compose.prod.yml build app
 docker save szeu-app-prod:latest | gzip > $FILE_PATH