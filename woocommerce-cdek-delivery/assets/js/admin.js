jQuery(document).ready(function($) {
    'use strict';
    
    // Test API Connection
    $('#test-connection').on('click', function() {
        var button = $(this);
        var result = $('#connection-result');
        
        button.prop('disabled', true).text('Тестирование...');
        result.html('<p>Проверка соединения с CDEK API...</p>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_test_connection',
                nonce: $('#cdek_admin_nonce').val() || $('[name="cdek_admin_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                } else {
                    result.html('<div class="notice notice-error"><p>❌ Ошибка: ' + response.data.message + '</p></div>');
                }
            },
            error: function(xhr, status, error) {
                result.html('<div class="notice notice-error"><p>❌ Ошибка соединения: ' + error + '</p></div>');
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text('Тест соединения');
            }
        });
    });
    
    // Sync Pickup Points
    $('#sync-offices').on('click', function() {
        var button = $(this);
        
        if (!confirm('Синхронизировать пункты выдачи? Это может занять некоторое время.')) {
            return;
        }
        
        button.prop('disabled', true).text('Синхронизация...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_sync_offices',
                nonce: $('#cdek_admin_nonce').val() || $('[name="cdek_admin_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload(); // Перезагружаем для обновления статистики
                } else {
                    alert('❌ Ошибка: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Ошибка синхронизации: ' + error);
                console.error('AJAX Error:', xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text('Синхронизировать пункты выдачи');
            }
        });
    });
    
    // Auto-update stats every 30 seconds on dashboard
    if ($('.cdek-stats-grid').length > 0) {
        setInterval(function() {
            updateStats();
        }, 30000);
    }
    
    function updateStats() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_get_stats',
                nonce: $('#cdek_admin_nonce').val() || $('[name="cdek_admin_nonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            },
            error: function() {
                console.error('Failed to update stats');
            }
        });
    }
    
    function updateStatsDisplay(stats) {
        $('.cdek-stat-card').each(function() {
            var card = $(this);
            var statType = card.data('stat-type');
            
            if (stats[statType] !== undefined) {
                card.find('.cdek-stat-number').text(stats[statType]);
                card.addClass('cdek-fade-in');
            }
        });
    }
    
    // Settings form validation
    $('form[action*="wc-settings"]').on('submit', function() {
        var account = $('input[name*="account"]').val();
        var password = $('input[name*="secure_password"]').val();
        
        if (!account || !password) {
            alert('⚠️ Пожалуйста, заполните все обязательные поля API');
            return false;
        }
        
        // Validate account format
        if (account.length < 10) {
            alert('⚠️ Идентификатор аккаунта должен содержать не менее 10 символов');
            return false;
        }
        
        // Validate password format
        if (password.length < 10) {
            alert('⚠️ Пароль должен содержать не менее 10 символов');
            return false;
        }
        
        return true;
    });
    
    // Enhanced tooltips
    $('[data-tooltip]').on('mouseenter', function() {
        var tooltip = $(this).attr('data-tooltip');
        var $tooltip = $('<div class="cdek-tooltip-popup">' + tooltip + '</div>');
        
        $('body').append($tooltip);
        
        var offset = $(this).offset();
        $tooltip.css({
            position: 'absolute',
            top: offset.top - $tooltip.outerHeight() - 10,
            left: offset.left + ($(this).outerWidth() / 2) - ($tooltip.outerWidth() / 2),
            zIndex: 9999
        });
    }).on('mouseleave', function() {
        $('.cdek-tooltip-popup').remove();
    });
    
    // Copy to clipboard functionality
    $('.cdek-copy-to-clipboard').on('click', function() {
        var text = $(this).data('copy-text') || $(this).prev('code').text();
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showNotice('Скопировано в буфер обмена', 'success');
            });
        } else {
            // Fallback for older browsers
            var textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotice('Скопировано в буфер обмена', 'success');
        }
    });
    
    // Show notice function
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible cdek-notice"><p>' + message + '</p></div>');
        
        $('.wrap h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                notice.remove();
            });
        }, 3000);
    }
    
    // Auto-save draft settings
    var saveTimeout;
    $('.cdek-settings input, .cdek-settings select').on('change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(function() {
            saveDraftSettings();
        }, 2000);
    });
    
    function saveDraftSettings() {
        var settings = {};
        $('.cdek-settings input, .cdek-settings select').each(function() {
            var name = $(this).attr('name');
            var value = $(this).val();
            
            if (name && value) {
                settings[name] = value;
            }
        });
        
        localStorage.setItem('cdek_draft_settings', JSON.stringify(settings));
    }
    
    // Load draft settings
    function loadDraftSettings() {
        var draft = localStorage.getItem('cdek_draft_settings');
        
        if (draft) {
            try {
                var settings = JSON.parse(draft);
                
                Object.keys(settings).forEach(function(name) {
                    var input = $('input[name="' + name + '"], select[name="' + name + '"]');
                    if (input.length && !input.val()) {
                        input.val(settings[name]);
                    }
                });
            } catch (e) {
                console.error('Failed to load draft settings:', e);
            }
        }
    }
    
    // Load draft settings on page load
    loadDraftSettings();
    
    // Clear draft settings on successful save
    $('form[action*="wc-settings"]').on('submit', function() {
        localStorage.removeItem('cdek_draft_settings');
    });
    
    // Enhanced order management
    window.createCdekOrder = function(orderId) {
        if (!confirm('Создать заказ в системе CDEK?')) {
            return;
        }
        
        var button = $('button[onclick*="createCdekOrder(' + orderId + ')"]');
        var originalText = button.text();
        
        button.prop('disabled', true).text('Создание...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_create_order',
                order_id: orderId,
                nonce: $('#cdek_admin_nonce').val() || $('[name="_wpnonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Заказ успешно создан в CDEK!');
                    location.reload();
                } else {
                    alert('❌ Ошибка: ' + (response.data ? response.data.message : 'Неизвестная ошибка'));
                }
            },
            error: function(xhr) {
                alert('❌ Ошибка создания заказа: ' + xhr.statusText);
                console.error('Create order error:', xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    window.updateCdekOrderStatus = function(orderId) {
        var button = $('button[onclick*="updateCdekOrderStatus(' + orderId + ')"]');
        var originalText = button.text();
        
        button.prop('disabled', true).text('Обновление...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_get_order_status',
                order_id: orderId,
                nonce: $('#cdek_admin_nonce').val() || $('[name="_wpnonce"]').val()
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Статус обновлен!');
                    if (response.data && response.data.reload) {
                        location.reload();
                    }
                } else {
                    alert('❌ Ошибка: ' + (response.data ? response.data.message : 'Неизвестная ошибка'));
                }
            },
            error: function(xhr) {
                alert('❌ Ошибка обновления статуса: ' + xhr.statusText);
                console.error('Update status error:', xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + T for test connection
        if ((e.ctrlKey || e.metaKey) && e.key === 't' && $('#test-connection').length) {
            e.preventDefault();
            $('#test-connection').click();
        }
        
        // Ctrl/Cmd + S for sync offices
        if ((e.ctrlKey || e.metaKey) && e.key === 's' && $('#sync-offices').length) {
            e.preventDefault();
            $('#sync-offices').click();
        }
    });
    
    // Print functionality
    $('.cdek-print-report').on('click', function() {
        window.print();
    });
    
    // Export functionality
    $('.cdek-export-data').on('click', function() {
        var type = $(this).data('export-type');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cdek_export_data',
                type: type,
                nonce: $('#cdek_admin_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    // Create download link
                    var link = document.createElement('a');
                    link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(response.data.csv);
                    link.download = 'cdek-' + type + '-export.csv';
                    link.click();
                } else {
                    alert('❌ Ошибка экспорта: ' + response.data.message);
                }
            },
            error: function() {
                alert('❌ Ошибка экспорта данных');
            }
        });
    });
    
    // Initialize tooltips and other UI enhancements
    function initUI() {
        // Add loading indicators where needed
        $('.cdek-async-action').on('click', function() {
            $(this).addClass('cdek-loading');
        });
        
        // Initialize sortable tables if needed
        if ($.fn.sortable && $('.cdek-sortable').length) {
            $('.cdek-sortable').sortable({
                axis: 'y',
                handle: '.cdek-sort-handle'
            });
        }
    }
    
    // Initialize UI on document ready
    initUI();
    
    // Re-initialize UI after AJAX updates
    $(document).ajaxComplete(function() {
        initUI();
    });
});