pipeline {
    agent any

    environment {
        IMAGE_NAME = "${env.JOB_NAME.toLowerCase()}"
        PIPELINE_IID = "${env.BUILD_NUMBER}"
    }

    options {
        buildDiscarder(logRotator(numToKeepStr: '10'))
        timestamps()
    }

    stages {
        stage('Setup') {
            steps {
                script {
                    // Determine environment based on branch
                    if (env.BRANCH_NAME == 'main' || env.GIT_BRANCH == 'origin/main') {
                        env.ENV_NAME = 'production'
                    } else if (env.BRANCH_NAME == 'staging' || env.GIT_BRANCH == 'origin/staging') {
                        env.ENV_NAME = 'staging'
                    } else {
                        echo "Branch not main or staging, skipping deployment"
                        currentBuild.result = 'NOT_BUILT'
                        return
                    }

                    env.HOST_CRED_ID = "${ENV_NAME}-host"
                    env.PORT_CRED_ID = "${ENV_NAME}-port"
                    env.ENV_CRED_ID = "${ENV_NAME}-env"
                }
            }
        }

        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Docker Image') {
            when {
                expression { currentBuild.result != 'NOT_BUILT' }
            }
            steps {
                script {
                    withCredentials([
                        usernamePassword(
                            credentialsId: 'container-registry',
                            usernameVariable: 'REGISTRY_USER',
                            passwordVariable: 'REGISTRY_PASSWORD'
                        ),
                        string(credentialsId: 'container-registry-url', variable: 'CONTAINER_REGISTRY')
                    ]) {
                        sh '''
                            set -e
                            echo "Building Docker image..."

                            # Login to container registry
                            echo "$REGISTRY_PASSWORD" | docker login -u "$REGISTRY_USER" --password-stdin "$CONTAINER_REGISTRY"

                            # Build and push Docker image
                            IMAGE_REPO="${CONTAINER_REGISTRY}/${REGISTRY_USER}/${IMAGE_NAME}"

                            # Build with BuildKit
                            DOCKER_BUILDKIT=1 docker build \\
                                -f Dockerfile \\
                                -t ${IMAGE_REPO}:${PIPELINE_IID} \\
                                .

                            # Tag with env name
                            docker tag ${IMAGE_REPO}:${PIPELINE_IID} ${IMAGE_REPO}:${ENV_NAME}

                            # Push both tags
                            docker push ${IMAGE_REPO}:${PIPELINE_IID}
                            docker push ${IMAGE_REPO}:${ENV_NAME}

                            echo "Docker image built and pushed successfully"
                        '''
                    }
                }
            }
        }

        stage('Deploy') {
            when {
                expression { currentBuild.result != 'NOT_BUILT' }
            }
            steps {
                script {
                    withCredentials([
                        sshUserPrivateKey(
                            credentialsId: 'deployer-ssh-key',
                            keyFileVariable: 'SSH_KEY'
                        ),
                        file(credentialsId: 'app-dotenv', variable: 'DOTENV_FILE'),
                        usernamePassword(
                            credentialsId: 'container-registry',
                            usernameVariable: 'REGISTRY_USER',
                            passwordVariable: 'REGISTRY_PASSWORD'
                        ),
                        string(credentialsId: 'container-registry-url', variable: 'CONTAINER_REGISTRY'),
                        string(credentialsId: 'deployment-host', variable: 'DEPLOYMENT_HOST'),
                        string(credentialsId: 'deployment-app-port', variable: 'DEPLOYMENT_APP_PORT')
                    ]) {
                        sh '''
                            set -e

                            echo "Starting deployment to ${ENV_NAME} environment..."

                            # Setup SSH
                            mkdir -p ~/.ssh
                            chmod 700 ~/.ssh

                            # Configure SSH
                            cat > ~/.ssh/config << EOF
Host deployment-host
    HostName ${DEPLOYMENT_HOST}
    User deployer
    IdentityFile ${SSH_KEY}
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
EOF
                            chmod 600 ~/.ssh/config

                            # Set variables for substitution
                            export DC_IMAGE_NAME="${CONTAINER_REGISTRY}/${REGISTRY_USER}/${IMAGE_NAME}"
                            export DC_IMAGE_TAG="${ENV_NAME}"
                            export DC_APP_PORT="${DEPLOYMENT_APP_PORT}"
                            export COMPOSE_PROJECT_NAME="${IMAGE_NAME}"

                            COMPOSE_FILE_DEST="/home/deployer/${IMAGE_NAME}/docker-compose.yml"

                            # Substitute variables in docker-compose
                            envsubst < ./docker-compose.yml > ./docker-compose.yml.tmp
                            mv ./docker-compose.yml.tmp ./docker-compose.yml

                            # Transfer docker-compose file
                            ssh -o StrictHostKeyChecking=no deployer@${DEPLOYMENT_HOST} "mkdir -p $(dirname $COMPOSE_FILE_DEST)"
                            rsync -e "ssh -o StrictHostKeyChecking=no" \
                                ./docker-compose.yml \
                                deployer@${DEPLOYMENT_HOST}:${COMPOSE_FILE_DEST}

                            # Transfer .env file
                            rsync -e "ssh -o StrictHostKeyChecking=no" \
                                ${DOTENV_FILE} \
                                deployer@${DEPLOYMENT_HOST}:$(dirname ${COMPOSE_FILE_DEST})/.env

                            # Deploy on remote server
                            ssh -o StrictHostKeyChecking=no deployer@${DEPLOYMENT_HOST} "
                                set -e &&
                                export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME} &&
                                export COMPOSE_FILE=${COMPOSE_FILE_DEST} &&
                                docker login -u ${REGISTRY_USER} -p ${REGISTRY_PASSWORD} ${CONTAINER_REGISTRY} &&
                                docker compose -f ${COMPOSE_FILE_DEST} pull &&
                                docker compose -f ${COMPOSE_FILE_DEST} up --force-recreate --remove-orphans -d &&
                                docker image prune -f
                            "

                            echo "Deployment completed successfully!"
                        '''
                    }
                }
            }
        }
    }

    post {
        always {
            cleanWs()
        }
        success {
            echo "Pipeline completed successfully"
        }
        failure {
            echo "Pipeline failed"
        }
    }
}
