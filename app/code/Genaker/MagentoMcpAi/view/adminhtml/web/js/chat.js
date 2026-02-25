import {
    html,
    render,
    signal,
    computed,
    effect
} from "./lib/standalone.js";

export class Chat {
    constructor(apiKey, mspiApiKey, baseUrl, html, signal, computed) {
        this.apiKey = apiKey;
        this.mspiApiKey = mspiApiKey;
        this.baseUrl = baseUrl;
        this.html = html;
        this.signal = signal;
        this.computed = computed;
        
        // Initialize signals
        this.messages = signal([{
            type: 'assistant',
            content: 'Hello! I can help you with your store data. Ask me anything!'
        }]);
        this.input = signal('');
        this.selectedModel = signal('gpt-3.5-turbo');
        this.resultGrid = signal('');
        this.currentPage = signal(1);
        this.rowsPerPage = signal(100);
        this.totalRows = signal(0);
        this.paginatedData = signal([]);
        this.showTokenStats = signal(false);
        this.tokenUsage = signal({
            prompt_tokens: 0,
            completion_tokens: 0,
            total_tokens: 0
        });
        
        // Bind methods to the instance
        this.exportChatToCSV = this.exportChatToCSV.bind(this);
        this.handleSend = this.handleSend.bind(this);
        this.clearChat = this.clearChat.bind(this);
        this.toggleTokenStats = this.toggleTokenStats.bind(this);
        this.closeTokenStats = this.closeTokenStats.bind(this);
    }

    async generateQuery(prompt, model) {
        try {
            const response = await fetch(`${this.baseUrl}rest/V1/magentomcpai/query`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    prompt: prompt,
                    model: model,
                    mspiApiKey: this.mspiApiKey
                }),
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `Server responded with status ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error in generateQuery:', error);
            throw error;
        }
    }

    async clearConversation() {
        try {
            const response = await fetch(`${this.baseUrl}rest/V1/magentomcpai/chat/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    mspiApiKey: this.mspiApiKey
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to clear conversation');
            }
            
            return data;
        } catch (error) {
            console.error('Error clearing conversation:', error);
            throw error;
        }
    }

    updateTokenUsage(usage) {
        this.tokenUsage.value = {
            prompt_tokens: this.tokenUsage.value.prompt_tokens + (usage.prompt_tokens || 0),
            completion_tokens: this.tokenUsage.value.completion_tokens + (usage.completion_tokens || 0),
            total_tokens: this.tokenUsage.value.total_tokens + (usage.total_tokens || 0)
        };
    }

    toggleTokenStats() {
        this.showTokenStats.value = !this.showTokenStats.value;
    }

    closeTokenStats() {
        this.showTokenStats.value = false;
    }

    async handleSend() {
        if (!this.input.value.trim()) return;
        
        const message = this.input.value;
        this.input.value = '';
        
        // Add user message to the chat
        this.messages.value = [...this.messages.value, {
            type: 'user',
            content: message
        }];
        
        try {
            const data = await this.generateQuery(message, this.selectedModel.value);
            
            // Update token usage if available
            if (data.token_usage) {
                this.updateTokenUsage(data.token_usage);
            }
            
            // Add assistant response to the chat
            this.messages.value = [...this.messages.value, {
                type: data.type || 'assistant',
                content: data.content,
                result: data.result
            }];
            
            // Update pagination if needed
            if (data.result && Array.isArray(data.result)) {
                this.totalRows.value = data.result.length;
                this.updatePaginatedData();
            }
            
        } catch (error) {
            console.error('Error:', error);
            this.messages.value = [...this.messages.value, {
                type: 'error',
                content: 'An error occurred while processing your request.'
            }];
        }
    }

    async clearChat() {
        // Clear messages
        this.messages.value = [{
            type: 'assistant',
            content: 'Hello! I can help you with your store data. Ask me anything!'
        }];
        
        // Clear input
        this.input.value = '';
        
        // Clear result grid
        this.resultGrid.value = '';
        
        // Reset pagination
        this.currentPage.value = 1;
        this.totalRows.value = 0;
        this.paginatedData.value = [];
        
        // Reset token usage
        this.tokenUsage.value = {
            prompt_tokens: 0,
            completion_tokens: 0,
            total_tokens: 0
        };
    }

    updatePaginatedData() {
        const start = (this.currentPage.value - 1) * this.rowsPerPage.value;
        const end = start + this.rowsPerPage.value;
        this.paginatedData.value = this.paginatedData.value.slice(start, end);
        this.updateResultGrid();
    }

    updateResultGrid() {
        if (this.paginatedData.value.length === 0) {
            this.resultGrid.value = '';
            return;
        }

        const headers = Object.keys(this.paginatedData.value[0]);
        let html = '<table class="data-grid"><thead><tr>';
        
        headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        
        html += '</tr></thead><tbody>';
        
        this.paginatedData.value.forEach(row => {
            html += '<tr>';
            headers.forEach(header => {
                html += `<td>${row[header] || ''}</td>`;
            });
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        
        if (this.totalRows.value > this.rowsPerPage.value) {
            html += this.renderPagination();
        }
        
        this.resultGrid.value = html;
    }

    renderPagination() {
        const totalPages = Math.ceil(this.totalRows.value / this.rowsPerPage.value);
        let html = '<div class="pagination">';
        
        if (this.currentPage.value > 1) {
            html += `<button onclick="chat.currentPage.value = ${this.currentPage.value - 1}; chat.updatePaginatedData();">Previous</button>`;
        }
        
        html += `<span>Page ${this.currentPage.value} of ${totalPages}</span>`;
        
        if (this.currentPage.value < totalPages) {
            html += `<button onclick="chat.currentPage.value = ${this.currentPage.value + 1}; chat.updatePaginatedData();">Next</button>`;
        }
        
        html += '</div>';
        return html;
    }

    exportChatToCSV() {
        const csv = [];
        // Add headers
        csv.push(['Type', 'Message']);
        
        // Add messages
        this.messages.value.forEach(msg => {
            // Properly escape message content for CSV
            let content = msg.content;
            if (content.includes(',') || content.includes('"') || content.includes('\n')) {
                content = `"${content.replace(/"/g, '""')}"`;
            }
            csv.push([msg.type, content]);
        });
        
        // Create and download CSV file
        const blob = new Blob([csv.map(row => row.join(',')).join('\n')], { 
            type: 'text/csv;charset=utf-8;' 
        });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        // Get current date for filename
        const date = new Date();
        const filename = `chat_history_${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}.csv`;
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    render() {
        return this.html`
            <div class="mcpai-container">
                ${!this.apiKey && this.html`
                    <div class="message message-warning">
                        Please configure your OpenAI API key in System > Configuration > Genaker > Magento MCP AI
                    </div>
                `}
                
                <div class="chat-section">
                    <div class="model-selector">
                        <label for="ai-model">Select AI Model:</label>
                        <select 
                            id="ai-model" 
                            class="admin__control-select"
                            value=${this.selectedModel.value}
                            onChange=${e => this.selectedModel.value = e.target.value}
                        >
                            <optgroup label="Free Models">
                                <option value="gpt-3.5-turbo">GPT-3.5 Turbo</option>
                                <option value="gpt-5-nano">GPT-5 Nano</option>
                            </optgroup>
                            <optgroup label="Paid Models">
                                <option value="gpt-4">GPT-4</option>
                                <option value="gpt-4-turbo">GPT-4 Turbo</option>
                                <option value="gpt-4-32k">GPT-4 32k</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="chat-messages">
                        ${this.messages.value.map((msg, index) => this.html`
                            <div key=${index} class=${msg.type + '-message'}>
                                ${msg.content}
                            </div>
                        `)}
                    </div>
                    
                    <div class="chat-input">
                        <textarea 
                            value=${this.input.value}
                            onInput=${e => this.input.value = e.target.value}
                            onKeyPress=${e => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    this.handleSend();
                                }
                            }}
                            placeholder="Ask me anything about your store data..."
                        ></textarea>
                        <div class="chat-buttons">
                            <button 
                                type="button" 
                                class="action-primary"
                                onClick=${this.handleSend}
                            >
                                Send
                            </button>
                            <button 
                                type="button" 
                                class="action-secondary"
                                onClick=${this.clearChat}
                            >
                                Clear
                            </button>
                            <button 
                                type="button" 
                                class="action-secondary"
                                onClick=${this.exportChatToCSV}
                                title="Save chat history as CSV"
                            >
                                <i class="fas fa-download"></i> Save Chat
                            </button>
                        </div>
                    </div>
                </div>
                
                ${this.resultGrid.value && this.html`
                    <div class="result-section">
                        <div class="result-content" dangerouslySetInnerHTML=${{ __html: this.resultGrid.value }} />
                    </div>
                `}
                
                ${this.showTokenStats.value ? this.html`
                    <div class="token-stats">
                        <div class="token-stats-header">
                            <span class="token-stats-title">Token Usage</span>
                            <span class="token-stats-close" onClick=${this.closeTokenStats.bind(this)}>×</span>
                        </div>
                        <div class="token-stats-content">
                            <div class="token-stat">
                                <div class="token-stat-value">${this.tokenUsage.value.prompt_tokens}</div>
                                <div class="token-stat-label">Prompt</div>
                            </div>
                            <div class="token-stat">
                                <div class="token-stat-value">${this.tokenUsage.value.completion_tokens}</div>
                                <div class="token-stat-label">Completion</div>
                            </div>
                            <div class="token-stat">
                                <div class="token-stat-value">${this.tokenUsage.value.total_tokens}</div>
                                <div class="token-stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                ` : this.html`
                    <button class="token-stats-toggle" onClick=${this.toggleTokenStats.bind(this)}>
                        <i class="fas fa-chart-bar"></i> Token Stats
                    </button>
                `}
            </div>
        `;
    }
} 