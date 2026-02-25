define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/storage',
    'mage/translate',
    'mage/url'
], function ($, ko, Component, storage, $t, urlBuilder) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Genaker_MagentoMcpAi/chatbot',
            logoUrl: '',
            title: 'Virtual Assistant',
            welcomeMessage: 'Hey there 👋 I\'m here to help you find what you need.',
            buttonText: 'Try our virtual assistant',
            suggestedQueries: [],
            storeContext: {},
            apiUrl: '',
            productPageDetection: true,
            productQuestionsEnabled: false,
            attributeBlacklist: []
        },

        // Observables
        isChatOpen: ko.observable(false),
        isLoading: ko.observable(false),
        messages: ko.observableArray([]),
        userInput: ko.observable(''),
        showSuggestions: ko.observable(true),
        chatHistory: ko.observableArray([]),

        // Initialize
        initialize: function () {
            this._super();
            
            // Load chat history from localStorage if available
            var savedHistory = localStorage.getItem('chatbot_history');
            if (savedHistory) {
                try {
                    this.chatHistory(JSON.parse(savedHistory));
                } catch (e) {
                    console.error('Error loading chat history:', e);
                    this.chatHistory([]);
                }
            }

            // Check if chatbot should auto-open based on URL parameters
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('open_chat') === 'true') {
                this.toggleChat();
            }
            
            // Detect product information if enabled
            if (this.productPageDetection) {
                this.detectProductInformation();
            }

            return this;
        },

        // Get template to use
        getTemplate: function () {
            return this.template;
        },

        // Toggle chat window
        toggleChat: function () {
            this.isChatOpen(!this.isChatOpen());
            if (this.isChatOpen() && this.messages().length === 0) {
                // Show welcome message when first opening
                this.showSuggestions(true);
            }

            // Scroll to bottom when opening
            if (this.isChatOpen()) {
                setTimeout(function () {
                    this.scrollToBottom();
                }.bind(this), 100);
            }
        },

        // Send message
        sendMessage: function () {
            if (!this.userInput().trim() || this.isLoading()) {
                return;
            }

            var userMessage = this.userInput().trim();
            this.addMessage(userMessage, true);
            this.userInput('');
            this.showSuggestions(false);
            this.fetchResponse(userMessage);
        },

        // Send a suggested query
        sendSuggestedQuery: function (query) {
            this.userInput(query);
            this.sendMessage();
        },

        // Add message to chat
        addMessage: function (text, isUser) {
            this.messages.push({
                text: text,
                isUser: isUser
            });

            this.chatHistory.push({
                text: text,
                isUser: isUser,
                timestamp: new Date().toISOString()
            });

            // Save to localStorage (keep last 50 messages)
            localStorage.setItem('chatbot_history', JSON.stringify(this.chatHistory.slice(-50)));

            // Scroll to bottom
            setTimeout(function () {
                this.scrollToBottom();
            }.bind(this), 100);
        },

        // Scroll chat to bottom
        scrollToBottom: function () {
            var chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        },

        // Format message (convert URLs to links)
        formatMessage: function (text) {
            var urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, function (url) {
                return '<a href="' + url + '" target="_blank" class="chatbot-link">' + url + '</a>';
            });
        },

        // Detect product information from the current page
        detectProductInformation: function() {
            try {
                // Skip if product questions are disabled
                if (!this.productQuestionsEnabled) {
                    return;
                }
                
                // First check if PRODUCT_INFO is defined globally
                if (typeof PRODUCT_INFO !== 'undefined' && PRODUCT_INFO) {
                    var filteredProduct = this.filterProductAttributes(PRODUCT_INFO);
                    this.storeContext.current_product = filteredProduct;
                    return;
                }
                
                // Otherwise try to extract product data from the DOM
                var productData = document.querySelector('[data-role="priceBox"]');
                if (productData) {
                    var productId = productData.getAttribute('data-product-id');
                    if (productId) {
                        var currentProduct = {
                            id: productId,
                            name: document.querySelector('.page-title span') ? 
                                document.querySelector('.page-title span').textContent : '',
                            price: document.querySelector('[data-price-type="finalPrice"] .price') ? 
                                document.querySelector('[data-price-type="finalPrice"] .price').textContent : '',
                            description: document.querySelector('.product.attribute.description .value') ? 
                                document.querySelector('.product.attribute.description .value').textContent : '',
                            attributes: {}
                        };
                        
                        // Try to collect product attributes
                        var attributes = document.querySelectorAll('.product.attribute');
                        if (attributes && attributes.length) {
                            attributes.forEach(function(attr) {
                                var label = attr.querySelector('.label');
                                var value = attr.querySelector('.value');
                                if (label && value) {
                                    var attrName = label.textContent.trim().replace(':', '');
                                    currentProduct.attributes[attrName] = value.textContent.trim();
                                }
                            });
                        }
                        
                        // Filter product attributes
                        var filteredProduct = this.filterProductAttributes(currentProduct);
                        
                        // Add to store context
                        if (!this.storeContext) {
                            this.storeContext = {};
                        }
                        this.storeContext.current_product = filteredProduct;
                    }
                }
            } catch (e) {
                console.error('Error detecting product information:', e);
            }
        },
        
        // Filter product attributes based on blacklist
        filterProductAttributes: function(productInfo) {
            if (!productInfo) {
                return {};
            }
            
            // Create a copy to avoid modifying the original
            var filteredInfo = JSON.parse(JSON.stringify(productInfo));
            
            // Get blacklist from config
            var blacklist = this.attributeBlacklist || [];
            
            // Filter top-level attributes
            for (var i = 0; i < blacklist.length; i++) {
                var attribute = blacklist[i];
                if (filteredInfo[attribute] !== undefined) {
                    delete filteredInfo[attribute];
                }
            }
            
            // Filter nested attributes if they exist
            if (filteredInfo.attributes && typeof filteredInfo.attributes === 'object') {
                for (var j = 0; j < blacklist.length; j++) {
                    var nestedAttr = blacklist[j];
                    if (filteredInfo.attributes[nestedAttr] !== undefined) {
                        delete filteredInfo.attributes[nestedAttr];
                    }
                }
            }
            
            return filteredInfo;
        },

        // Fetch AI response
        fetchResponse: function (query) {
            this.isLoading(true);

            // Get store context
            var storeContext = this.storeContext || {};
            var apiUrl = this.apiUrl || urlBuilder.build('magentomcpai/chat/query');
            
            // Process conversation history for context
            var conversationHistory = [];
            var conversationSummary = '';
            
            var history = this.chatHistory();
            // If we have more than 10 messages, include a summary instead of full history
            if (history.length > 10) {
                // Get the last 5 messages
                var recentMessages = history.slice(-5);
                
                // Create a summary for older messages
                conversationSummary = "Previous conversation summary: ";
                var olderMessages = history.slice(0, -5);
                for (var i = 0; i < olderMessages.length; i++) {
                    var msg = olderMessages[i];
                    conversationSummary += (msg.isUser ? "User: " : "Assistant: ") + msg.text + "; ";
                }
                
                // Include only recent messages in the detailed history
                conversationHistory = recentMessages
                    .filter(function(msg) { return msg.text !== query; }) // Exclude current query
                    .map(function(msg) {
                        return {
                            role: msg.isUser ? 'user' : 'assistant',
                            content: msg.text
                        };
                    });
            } else {
                // If we have 10 or fewer messages, send them all
                conversationHistory = history
                    .filter(function(msg) { return msg.text !== query; }) // Exclude current query
                    .map(function(msg) {
                        return {
                            role: msg.isUser ? 'user' : 'assistant',
                            content: msg.text
                        };
                    });
            }

            storage.post(
                apiUrl,
                JSON.stringify({
                    query: query,
                    context: storeContext,
                    history: conversationHistory,
                    summary: conversationSummary
                }),
                true,
                'application/json'
            ).done(function (response) {
                this.isLoading(false);
                if (response.success) {
                    this.addMessage(response.message);
                } else {
                    this.addMessage($t('Sorry, I encountered an error. Please try again later.'));
                }
            }.bind(this)).fail(function () {
                this.isLoading(false);
                this.addMessage($t('Sorry, I encountered an error. Please try again later.'));
            }.bind(this));
        }
    });
});
