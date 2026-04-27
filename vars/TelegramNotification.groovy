def call(String status) {
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
