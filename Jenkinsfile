pipeline {
    agent { label 'docker' }

    options {
        disableConcurrentBuilds()
        timestamps()
    }

    stages {
        stage('Docker login') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: 'gh-pat',
                    usernameVariable: 'GHCR_USERNAME',
                    passwordVariable: 'GHCR_TOKEN'
                )]) {
                    sh '''
                        echo "$GHCR_TOKEN" |
                            docker login ghcr.io \
                                --username "$GHCR_USERNAME" \
                                --password-stdin
                    '''
                }
            }
        }

        stage('Prepare') {
            steps {
                script {
                    env.COMPOSE_PROJECT_NAME = "core-ci-${java.util.UUID.randomUUID().toString()}"
                }
                sh '''
                    docker compose \
                        --file docker-compose.yml \
                        --file docker-compose.ci.yml \
                        --project-name "$COMPOSE_PROJECT_NAME" \
                        run --rm \
                        --user "$(id -u):$(id -g)" \
                        web sh -lc 'export COMPOSER_HOME=/tmp/composer && mkdir -p "$COMPOSER_HOME" && composer validate --no-check-publish && composer install --no-interaction --prefer-dist'
                '''
            }
        }

        stage('Test') {
            steps {
                sh '''
                    docker compose \
                        --file docker-compose.yml \
                        --file docker-compose.ci.yml \
                        --project-name "$COMPOSE_PROJECT_NAME" \
                        run --rm \
                        --user "$(id -u):$(id -g)" \
                        web sh -lc 'XDEBUG_MODE=coverage vendor/bin/codecept run'
                '''
            }
        }
    }

    post {
        always {
            sh 'docker logout ghcr.io || true'
            script {
                if (env.COMPOSE_PROJECT_NAME) {
                    sh '''
                        docker compose \
                            --file docker-compose.yml \
                            --file docker-compose.ci.yml \
                            --project-name "$COMPOSE_PROJECT_NAME" \
                            down --volumes --remove-orphans || true
                    '''
                }
            }
        }
    }
}
