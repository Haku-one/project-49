jQuery(document).ready(function($) {
    'use strict';
    
    var cdekMap = null;
    var cdekPlacemarks = [];
    var selectedOffice = null;
    
    // Global function for initialization
    window.initCdekDelivery = function() {
        console.log('Initializing CDEK delivery functionality');
        
        // Bind events
        bindCdekEvents();
        
        // Check shipping method
        handleShippingMethodChange();
        
        // Observe shipping changes
        observeShippingChanges();
    };
    
    function bindCdekEvents() {
        // Search offices
        $(document).off('click', '#cdek-search-btn').on('click', '#cdek-search-btn', function() {
            var city = $('#cdek-city-search').val().trim();
            if (city) {
                searchOffices(city);
            }
        });
        
        $(document).off('keypress', '#cdek-city-search').on('keypress', '#cdek-city-search', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                var city = $(this).val().trim();
                if (city) {
                    searchOffices(city);
                }
            }
        });
        
        // Click on office
        $(document).off('click', '.cdek-office-item').on('click', '.cdek-office-item', function() {
            var office = JSON.parse($(this).attr('data-office'));
            selectOffice(office);
            
            if (cdekMap && office.latitude && office.longitude) {
                cdekMap.setCenter([parseFloat(office.latitude), parseFloat(office.longitude)], 15);
            }
        });
        
        // Auto-fill city
        setTimeout(function() {
            var city = getShippingCity();
            if (city) {
                $('#cdek-city-search').val(city);
            }
        }, 1000);
    }
    
    function observeShippingChanges() {
        // Listen for shipping method changes
        $(document).on('change', 'input[name*="shipping_method"]', function() {
            setTimeout(handleShippingMethodChange, 500);
        });
        
        // Listen for clicks on shipping options
        $(document).on('click', '.wc-block-checkout__shipping-method-option, .shipping_method', function() {
            setTimeout(handleShippingMethodChange, 500);
        });
        
        // Observe attribute changes
        var shippingObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && 
                    (mutation.attributeName === 'aria-checked' || mutation.attributeName === 'checked')) {
                    setTimeout(handleShippingMethodChange, 100);
                }
            });
        });
        
        // Start observing
        var shippingMethods = document.querySelectorAll('.wc-block-checkout__shipping-method-option, .shipping_method');
        shippingMethods.forEach(function(method) {
            shippingObserver.observe(method, { attributes: true });
        });
        
        // Periodic checks
        setInterval(handleShippingMethodChange, 3000);
    }
    
    function handleShippingMethodChange() {
        var cdekSelector = $('.cdek-pickup-selector');
        
        // Check for CDEK shipping method
        var isCdekSelected = false;
        
        // Check block checkout
        var selectedShippingText = $('.wc-block-checkout__shipping-method-option--selected .wc-block-checkout__shipping-method-option-title').text();
        if (selectedShippingText && selectedShippingText.toLowerCase().indexOf('cdek') !== -1) {
            isCdekSelected = true;
        }
        
        // Check classic checkout
        var selectedClassicMethod = $('input[name*="shipping_method"]:checked').val();
        if (selectedClassicMethod && selectedClassicMethod.indexOf('cdek_delivery') !== -1) {
            isCdekSelected = true;
        }
        
        // Check by method label
        var selectedMethodLabel = $('input[name*="shipping_method"]:checked').next('label').text();
        if (selectedMethodLabel && selectedMethodLabel.toLowerCase().indexOf('cdek') !== -1) {
            isCdekSelected = true;
        }
        
        console.log('CDEK shipping selected:', isCdekSelected);
        console.log('Selected shipping text:', selectedShippingText);
        console.log('Selected classic method:', selectedClassicMethod);
        
        if (isCdekSelected) {
            cdekSelector.show();
            
            if (!cdekMap) {
                setTimeout(initMap, 500);
            }
            
            // Auto-fill city and search
            var city = getShippingCity();
            if (city && city !== $('#cdek-city-search').val()) {
                $('#cdek-city-search').val(city);
                if (!selectedOffice) {
                    searchOffices(city);
                }
            }
        } else {
            cdekSelector.hide();
            selectedOffice = null;
            $('#cdek-selected-office').val('');
        }
    }
    
    function getShippingCity() {
        // Look for city field in block checkout
        var cityField = $('#shipping-city');
        if (cityField.length && cityField.val()) {
            return cityField.val();
        }
        
        // Look for city field in classic checkout
        var classicCityField = $('#shipping_city');
        if (classicCityField.length && classicCityField.val()) {
            return classicCityField.val();
        }
        
        // Alternative search
        var cityInputs = $('input[type="text"]').filter(function() {
            var id = $(this).attr('id') || '';
            var name = $(this).attr('name') || '';
            return id.includes('city') || name.includes('city');
        });
        
        if (cityInputs.length) {
            var value = cityInputs.first().val();
            if (value) {
                return value;
            }
        }
        
        return 'Москва'; // Default
    }
    
    // Initialize map
    function initMap() {
        if (typeof ymaps === 'undefined') {
            console.error('Yandex Maps API not loaded');
            return;
        }
        
        ymaps.ready(function() {
            if (!document.getElementById('cdek-map')) {
                return;
            }
            
            cdekMap = new ymaps.Map('cdek-map', {
                center: [55.76, 37.64], // Moscow by default
                zoom: 10,
                controls: ['zoomControl', 'fullscreenControl']
            });
            
            console.log('Yandex Map initialized');
        });
    }
    
    // Search offices
    function searchOffices(city) {
        if (!city) {
            showError('Введите название города');
            return;
        }
        
        console.log('Searching offices for city:', city);
        
        showLoading();
        
        $.ajax({
            url: cdek_delivery_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_get_offices',
                city: city,
                nonce: cdek_delivery_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                hideLoading();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayOffices(response.data);
                    displayOfficesOnMap(response.data);
                    $('#cdek-map-container').show();
                    $('#cdek-offices-list').show();
                } else {
                    var errorMsg = response.data ? response.data.message : 'Пункты выдачи не найдены';
                    showError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                hideLoading();
                showError('Ошибка загрузки данных: ' + error);
            }
        });
    }
    
    // Display offices list
    function displayOffices(offices) {
        var container = $('.cdek-offices-container');
        container.empty();
        
        offices.forEach(function(office) {
            var officeHtml = '<div class="cdek-office-item" data-office=\'' + JSON.stringify(office) + '\'>';
            officeHtml += '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
            officeHtml += '<div class="cdek-office-address">' + office.address + '</div>';
            
            if (office.phone) {
                officeHtml += '<div class="cdek-office-phone">Телефон: ' + office.phone + '</div>';
            }
            
            if (office.work_time) {
                officeHtml += '<div class="cdek-office-hours">' + office.work_time + '</div>';
            }
            
            officeHtml += '</div>';
            
            container.append(officeHtml);
        });
    }
    
    // Display offices on map
    function displayOfficesOnMap(offices) {
        if (!cdekMap || !ymaps) {
            return;
        }
        
        // Clear previous placemarks
        cdekPlacemarks.forEach(function(placemark) {
            cdekMap.geoObjects.remove(placemark);
        });
        cdekPlacemarks = [];
        
        var bounds = [];
        
        offices.forEach(function(office) {
            if (office.latitude && office.longitude) {
                var coords = [parseFloat(office.latitude), parseFloat(office.longitude)];
                bounds.push(coords);
                
                var placemark = new ymaps.Placemark(coords, {
                    balloonContentHeader: office.name || 'CDEK ' + office.code,
                    balloonContentBody: office.address + (office.phone ? '<br>Телефон: ' + office.phone : ''),
                    balloonContentFooter: office.work_time || '',
                    hintContent: office.address
                }, {
                    preset: 'islands#redDotIcon'
                });
                
                placemark.events.add('click', function() {
                    selectOffice(office);
                });
                
                cdekMap.geoObjects.add(placemark);
                cdekPlacemarks.push(placemark);
            }
        });
        
        // Set map bounds
        if (bounds.length > 0) {
            cdekMap.setBounds(bounds, {
                checkZoomRange: true,
                zoomMargin: 20
            });
        }
    }
    
    // Select office
    function selectOffice(office) {
        selectedOffice = office;
        
        // Update UI
        $('.cdek-office-item').removeClass('selected');
        $('.cdek-office-item').each(function() {
            var itemOffice = JSON.parse($(this).attr('data-office'));
            if (itemOffice.code === office.code) {
                $(this).addClass('selected');
            }
        });
        
        // Show selected office info
        displaySelectedOffice(office);
        
        // Save to hidden field
        $('#cdek-selected-office').val(JSON.stringify(office));
        
        console.log('Office selected:', office);
    }
    
    // Display selected office
    function displaySelectedOffice(office) {
        var html = '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
        html += '<div class="cdek-office-address">Адрес: ' + office.address + '</div>';
        
        if (office.phone) {
            html += '<div class="cdek-office-phone">Телефон: ' + office.phone + '</div>';
        }
        
        if (office.work_time) {
            html += '<div class="cdek-office-hours">Время работы: ' + office.work_time + '</div>';
        }
        
        $('.cdek-office-details').html(html);
        $('#cdek-selected-office-info').show();
    }
    
    // Show loading
    function showLoading() {
        $('.cdek-offices-container').html('<div class="cdek-loading">Загрузка...</div>');
    }
    
    // Hide loading
    function hideLoading() {
        // Loading will be hidden when results are displayed
    }
    
    // Show error
    function showError(message) {
        $('.cdek-offices-container').html('<div class="cdek-error">' + message + '</div>');
    }
    
    // Validation on order submit
    $(document).on('click', '.wc-block-components-checkout-place-order-button, #place_order', function(e) {
        // Check if CDEK is selected
        var isCdekSelected = false;
        
        var selectedShippingText = $('.wc-block-checkout__shipping-method-option--selected .wc-block-checkout__shipping-method-option-title').text();
        if (selectedShippingText && selectedShippingText.toLowerCase().indexOf('cdek') !== -1) {
            isCdekSelected = true;
        }
        
        var selectedClassicMethod = $('input[name*="shipping_method"]:checked').val();
        if (selectedClassicMethod && selectedClassicMethod.indexOf('cdek_delivery') !== -1) {
            isCdekSelected = true;
        }
        
        if (isCdekSelected && !selectedOffice) {
            e.preventDefault();
            alert('Пожалуйста, выберите пункт выдачи CDEK');
            return false;
        }
    });
    
    // Initialize on load
    setTimeout(function() {
        if (typeof window.initCdekDelivery === 'function') {
            window.initCdekDelivery();
        }
    }, 2000);
});