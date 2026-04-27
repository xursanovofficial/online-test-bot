def sendTelegramNotification(String status) {
    try {
        withCredentials([
            string(credentialsId: 'telegram-bot-token', variable: 'TG_BOT_TOKEN'),
            string(credentialsId: 'telegram-chat-id', variable: 'TG_CHAT_ID')
        ]) {
            // Status emoji and label
            def emoji
            def statusText
            switch (status) {
                case 'SUCCESS':
                    emoji = '✅'
                    statusText = 'SUCCESS'
                    break
                case 'FAILURE':
                    emoji = '❌'
                    statusText = 'FAILED'
                    break
                case 'ABORTED':
                    emoji = '⚠️'
                    statusText = 'ABORTED'
                    break
                case 'UNSTABLE':
                    emoji = '⚡'
                    statusText = 'UNSTABLE'
                    break
                case 'NOT_BUILT':
                    emoji = 'ℹ️'
                    statusText = 'SKIPPED'
                    break
                default:
                    emoji = '❔'
                    statusText = status
            }

            // Gather build info
            def jobName = env.JOB_NAME ?: 'unknown'
            def buildNumber = env.BUILD_NUMBER ?: 'N/A'
            def branch = env.BRANCH_NAME ?: env.GIT_BRANCH ?: 'unknown'
            def envName = env.ENV_NAME ?: 'N/A'
            def buildUrl = env.BUILD_URL ?: ''
            def duration = currentBuild.durationString?.replace(' and counting', '') ?: 'N/A'

            // Git commit info (safe extraction)
            def commitHash = ''
            def commitMessage = ''
            def commitAuthor = ''
            try {
                commitHash = sh(script: 'git rev-parse --short HEAD 2>/dev/null || echo ""', returnStdout: true).trim()
                commitMessage = sh(script: 'git log -1 --pretty=%s 2>/dev/null || echo ""', returnStdout: true).trim()
                commitAuthor = sh(script: 'git log -1 --pretty=%an 2>/dev/null || echo ""', returnStdout: true).trim()
            } catch (Exception e) {
                echo "Could not get git info: ${e.message}"
            }

            // Escape special characters for Telegram MarkdownV2-safe HTML
            def escapeHtml = { String text ->
                if (text == null) return ''
                return text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
            }

            // Build the message (HTML format)
            def message = """${emoji} <b>${escapeHtml(statusText)}</b>

<b>📦 Project:</b> ${escapeHtml(jobName)}
<b>🔢 Build:</b> #${buildNumber}
<b>🌿 Branch:</b> <code>${escapeHtml(branch)}</code>
<b>🚀 Environment:</b> <code>${escapeHtml(envName)}</code>
<b>⏱ Duration:</b> ${escapeHtml(duration)}"""

            if (commitHash) {
                message += "\n<b>🔖 Commit:</b> <code>${escapeHtml(commitHash)}</code>"
            }
            if (commitAuthor) {
                message += "\n<b>👤 Author:</b> ${escapeHtml(commitAuthor)}"
            }
            if (commitMessage) {
                def shortMsg = commitMessage.length() > 100 ? commitMessage.substring(0, 100) + '...' : commitMessage
                message += "\n<b>💬 Message:</b> <i>${escapeHtml(shortMsg)}</i>"
            }
            if (buildUrl) {
                message += "\n\n<a href=\"${buildUrl}\">🔗 Open Build</a>"
            }

            // Send to Telegram (write to file to avoid shell escaping issues)
            writeFile file: 'tg_message.txt', text: message
            sh '''
                set +e
                curl -sS --max-time 15 \
                    -X POST "https://api.telegram.org/bot${TG_BOT_TOKEN}/sendMessage" \
                    --data-urlencode "chat_id=${TG_CHAT_ID}" \
                    --data-urlencode "text@tg_message.txt" \
                    --data-urlencode "parse_mode=HTML" \
                    --data-urlencode "disable_web_page_preview=true" \
                    -o /dev/null -w "Telegram HTTP: %{http_code}\n" || true
                rm -f tg_message.txt
            '''
        }
    } catch (Exception e) {
        // Never fail the pipeline because of notification issues
        echo "Telegram notification failed: ${e.message}"
    }
}

pipeline {
    agent {
        label 'dind'
    }

    environment {
        IMAGE_NAME = "${env.JOB_NAME.split('/')[0].toLowerCase()}"
        // IMAGE_NAME = "${env.JOB_NAME.toLowerCase()}"
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
                            credentialsId: 'github-token',
                            usernameVariable: 'GITHUB_USER',
                            passwordVariable: 'GITHUB_TOKEN'
                        )
                    ]) {
                        sh '''
                            set -e
                            echo "Building Docker image..."

                            # Login to GitHub Container Registry
                            echo "$GITHUB_TOKEN" | docker login -u "$GITHUB_USER" --password-stdin ghcr.io

                            # Build and push Docker image
                            IMAGE_REPO="ghcr.io/xursanovofficial/${IMAGE_NAME}"

                            # Build with BuildKit
                            DOCKER_BUILDKIT=1 docker build \\
                                -f Dockerfile \\
                                -t ${IMAGE_REPO}:${PIPELINE_IID} \\
                                .

                            # Tag with env name
                            docker tag ${IMAGE_REPO}:${PIPELINE_IID} ${IMAGE_REPO}:${ENV_NAME}
                            docker tag ${IMAGE_REPO}:${PIPELINE_IID} ${IMAGE_REPO}:latest

                            # Push all tags
                            docker push ${IMAGE_REPO}:${PIPELINE_IID}
                            docker push ${IMAGE_REPO}:${ENV_NAME}
                            docker push ${IMAGE_REPO}:latest

                            echo "Docker image built and pushed successfully to GitHub Container Registry"
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
                        file(credentialsId: env.ENV_CRED_ID, variable: 'DOTENV_FILE'),
                        usernamePassword(
                            credentialsId: 'github-token',
                            usernameVariable: 'GITHUB_USER',
                            passwordVariable: 'GITHUB_TOKEN'
                        ),
                        string(credentialsId: env.HOST_CRED_ID, variable: 'DEPLOYMENT_HOST'),
                        string(credentialsId: env.PORT_CRED_ID, variable: 'DEPLOYMENT_APP_PORT')
                    ]) {
                        sh '''
                            set -e

                            echo "Starting deployment to ${ENV_NAME} environment..."

                            # Setup SSH directory and copy key with proper permissions
                            mkdir -p ~/.ssh
                            chmod 700 ~/.ssh
                            cp "${SSH_KEY}" ~/.ssh/deployer_key
                            chmod 600 ~/.ssh/deployer_key

                            # SSH options used everywhere
                            SSH_OPTS="-i $HOME/.ssh/deployer_key -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o IdentitiesOnly=yes"

                            # Set variables for substitution
                            export DC_IMAGE_NAME="ghcr.io/xursanovofficial/${IMAGE_NAME}"
                            export DC_IMAGE_TAG="${ENV_NAME}"
                            export DC_APP_PORT="${DEPLOYMENT_APP_PORT}"
                            export COMPOSE_PROJECT_NAME="${IMAGE_NAME}"

                            COMPOSE_FILE_DEST="/home/deployer/${IMAGE_NAME}/docker-compose.yml"

                            # Substitute variables in docker-compose
                            envsubst < ./docker-compose.yml > ./docker-compose.yml.tmp
                            mv ./docker-compose.yml.tmp ./docker-compose.yml

                            # Transfer docker-compose file
                            ssh $SSH_OPTS deployer@${DEPLOYMENT_HOST} "mkdir -p $(dirname $COMPOSE_FILE_DEST)"
                            rsync -e "ssh $SSH_OPTS" \
                                ./docker-compose.yml \
                                deployer@${DEPLOYMENT_HOST}:${COMPOSE_FILE_DEST}

                            # Transfer .env file
                            rsync -e "ssh $SSH_OPTS" \
                                ${DOTENV_FILE} \
                                deployer@${DEPLOYMENT_HOST}:$(dirname ${COMPOSE_FILE_DEST})/.env

                            # Deploy on remote server
                            ssh $SSH_OPTS deployer@${DEPLOYMENT_HOST} "
                                set -e &&
                                export COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME} &&
                                export COMPOSE_FILE=${COMPOSE_FILE_DEST} &&
                                docker login -u ${GITHUB_USER} -p ${GITHUB_TOKEN} ghcr.io &&
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
        success {
            script {
                // Skip notification if deployment was skipped (non-deploy branches)
                if (currentBuild.result != 'NOT_BUILT') {
                    sendTelegramNotification('SUCCESS')
                }
            }
        }
        failure {
            script {
                sendTelegramNotification('FAILURE')
            }
        }
        aborted {
            script {
                sendTelegramNotification('ABORTED')
            }
        }
        unstable {
            script {
                sendTelegramNotification('UNSTABLE')
            }
        }
        always {
            cleanWs()
        }
    }
}