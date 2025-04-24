document.addEventListener('DOMContentLoaded', function() {
    //// Elements
    const addNewBtn = document.getElementById('abc-add-new');
    const bannersTable = document.querySelector('.abc-banners-table');
    const bannerEditor = document.querySelector('.abc-banner-editor');
    const cancelEditBtn = document.getElementById('abc-cancel-edit');
    const saveBannerBtn = document.getElementById('abc-save-banner');
    const addSlideBtn = document.getElementById('abc-add-slide');
    const slidesContainer = document.getElementById('abc-slides-container');

    // Show editor when Add New is clicked
    addNewBtn?.addEventListener('click', function() {
        bannersTable.style.display = 'none';
        bannerEditor.style.display = 'block';
        resetEditor();
    });

    // Cancel editing
    cancelEditBtn?.addEventListener('click', function() {
        bannersTable.style.display = 'block';
        bannerEditor.style.display = 'none';
    });

    // Add new slide
    addSlideBtn?.addEventListener('click', function() {
        addSlide();
    });

    // Save banner
    saveBannerBtn?.addEventListener('click', function() {
        saveBanner();
    });

    // Edit banner
    document.addEventListener('click', function(e) {
        if (e.target.matches('.abc-edit-banner')) {
            e.preventDefault();
            const bannerId = e.target.dataset.id;
            getBanner(bannerId);
        }
    });

    // Delete banner
    document.addEventListener('click', function(e) {
        if (e.target.matches('.abc-delete-banner')) {
            e.preventDefault();
            const bannerId = e.target.dataset.id;
            if (confirm('Are you sure you want to delete this banner?')) {
                deleteBanner(bannerId);
            }
        }
    });

    // Remove slide
    document.addEventListener('click', function(e) {
        if (e.target.matches('.abc-remove-slide')) {
            e.preventDefault();
            e.target.closest('.abc-slide').remove();
        }
    });

    // Media uploader for images
    document.addEventListener('click', function(e) {
        if (e.target.matches('.abc-upload-image')) {
            e.preventDefault();
            const button = e.target;
            const inputField = button.nextElementSibling;
            const preview = inputField.nextElementSibling;

            const frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                inputField.value = attachment.url;
                inputField.dispatchEvent(new Event('change'));
                preview.style.display = 'block';
                preview.querySelector('img').src = attachment.url;
            });

            frame.open();
        }
    });

    // Image preview when URL changes
    document.addEventListener('change', function(e) {
        if (e.target.matches('.abc-slide-image')) {
            const preview = e.target.nextElementSibling;
            const imageUrl = e.target.value;

            if (imageUrl) {
                preview.style.display = 'block';
                preview.querySelector('img').src = imageUrl;
            } else {
                preview.style.display = 'none';
            }
        }
    });

    // Generate slug from name
    document.getElementById('abc-banner-name')?.addEventListener('blur', function() {
        const slugInput = document.getElementById('abc-banner-slug');
    if (!slugInput.value) {
        const slug = this.value.toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)/g, '');
        slugInput.value = slug;
    }
});

    // Add a new slide to the editor
    function addSlide(slideData = {}) {
        const slideId = Date.now();
        const slideHtml = `
            <div class="abc-slide" data-id="${slideId}">
                <div class="abc-form-group">
                    <label>Slide Title</label>
                    <input type="text" class="abc-slide-title regular-text" value="${slideData.title || ''}">
                </div>
                
                <div class="abc-form-group">
                    <label>Link URL</label>
                    <input type="text" class="abc-slide-link regular-text" value="${slideData.link || ''}">
                </div>
                
                <div class="abc-form-group">
                    <label>Image URL</label>
                    <button class="button abc-upload-image">Upload</button>
                    <input type="text" class="abc-slide-image regular-text" value="${slideData.image || ''}">
                    <div class="abc-image-preview" style="${slideData.image ? '' : 'display:none;'}">
                        <img src="${slideData.image || ''}" style="max-width:200px; height:auto;">
                    </div>
                </div>
                
                <div class="abc-form-group">
                    <label>Alt Text</label>
                    <input type="text" class="abc-slide-alt regular-text" value="${slideData.alt_text || ''}">
                </div>
                
                <button class="button abc-remove-slide">Remove Slide</button>
                <hr>
            </div>
        `;
        slidesContainer.insertAdjacentHTML('beforeend', slideHtml);
    }

    // Reset editor to empty state
    function resetEditor() {
        document.getElementById('abc-banner-name').value = '';
        document.getElementById('abc-banner-slug').value = '';
        slidesContainer.innerHTML = '';
        bannerEditor.removeAttribute('data-banner-id');

        // Reset settings to defaults
        const defaultSettings = JSON.parse(abc_admin_vars.default_settings);
        document.getElementById('abc-autoplay').checked = defaultSettings.autoplay;
        document.getElementById('abc-autoplay-speed').value = defaultSettings.autoplay_speed;
        document.getElementById('abc-animation-speed').value = defaultSettings.animation_speed;
        document.getElementById('abc-pause-on-hover').checked = defaultSettings.pause_on_hover;
        document.getElementById('abc-infinite-loop').checked = defaultSettings.infinite_loop;
        document.getElementById('abc-show-arrows').checked = defaultSettings.show_arrows;
        document.getElementById('abc-show-dots').checked = defaultSettings.show_dots;
        document.getElementById('abc-slides-to-show').value = defaultSettings.slides_to_show;
        document.getElementById('abc-variable-width').checked = defaultSettings.variable_width;
    }

    // Get banner data via AJAX
    async function getBanner(bannerId) {
        try {
            const response = await fetch(abc_admin_vars.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'abc_get_banner',
                    nonce: abc_admin_vars.nonce,
                    id: bannerId
                })
            });

            const data = await response.json();

            if (data.success) {
                loadBannerIntoEditor(data.data);
                bannersTable.style.display = 'none';
                bannerEditor.style.display = 'block';
            } else {
                alert('Error: ' + data.data);
            }
        } catch (error) {
            alert('Request failed');
            console.error('Error:', error);
        }
    }


  // Generate slug from name in real-time
document.getElementById('abc-banner-name')?.addEventListener('input', function() {
    const name = this.value.trim();
    const slugInput = document.getElementById('abc-banner-slug');
    
    if (name && !slugInput.value) {
        const generatedSlug = name.toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
        
        slugInput.value = generatedSlug;
    }
});

    // Load banner data into editor
    function loadBannerIntoEditor(banner) {
        document.getElementById('abc-banner-name').value = banner.name;
        document.getElementById('abc-banner-slug').value = banner.slug;
        bannerEditor.setAttribute('data-banner-id', banner.id);

        // Load settings
        if (banner.settings) {
            document.getElementById('abc-autoplay').checked = banner.settings.autoplay;
            document.getElementById('abc-autoplay-speed').value = banner.settings.autoplay_speed;
            document.getElementById('abc-animation-speed').value = banner.settings.animation_speed;
            document.getElementById('abc-pause-on-hover').checked = banner.settings.pause_on_hover;
            document.getElementById('abc-infinite-loop').checked = banner.settings.infinite_loop;
            document.getElementById('abc-show-arrows').checked = banner.settings.show_arrows;
            document.getElementById('abc-show-dots').checked = banner.settings.show_dots;
            document.getElementById('abc-slides-to-show').value = banner.settings.slides_to_show;
            document.getElementById('abc-variable-width').checked = banner.settings.variable_width;
        }

        // Load slides
        slidesContainer.innerHTML = '';
        if (banner.slides && banner.slides.length > 0) {
            banner.slides.forEach(slide => addSlide(slide));
        }
    }

    // Save banner via AJAX
    async function saveBanner() {
    let name = document.getElementById('abc-banner-name').value.trim();
    let slug = document.getElementById('abc-banner-slug').value.trim();

    // Enhanced slug generation with fallback
    if (name) {
        // Generate slug if empty or invalid
        if (!slug) {
            slug = name.toLowerCase()
                .replace(/\s+/g, '-')           // Spaces to dashes
                .replace(/[^\w\-]+/g, '')       // Remove non-word chars
                .replace(/\-\-+/g, '-')         // Replace multiple dashes
                .replace(/^-+/, '')             // Trim start
                .replace(/-+$/, '');            // Trim end
            
            // Fallback for empty slugs
            if (!slug) {
                slug = 'banner-' + Date.now();
            }
            
            document.getElementById('abc-banner-slug').value = slug;
            slug = slug.trim();
        }
    }

    // Final validation
    if (!name || !slug) {
        alert('Please enter both name and slug');
        return;
    }

      

        // Collect slides data
        const slides = Array.from(document.querySelectorAll('.abc-slide')).map(slide => ({
            title: slide.querySelector('.abc-slide-title').value,
            link: slide.querySelector('.abc-slide-link').value,
            image: slide.querySelector('.abc-slide-image').value,
            alt_text: slide.querySelector('.abc-slide-alt').value
        }));

        if (slides.length === 0) {
            alert('Please add at least one slide');
            return;
        }

        // Collect settings
        const settings = {
            autoplay: document.getElementById('abc-autoplay').checked,
            autoplay_speed: parseInt(document.getElementById('abc-autoplay-speed').value),
            animation_speed: parseInt(document.getElementById('abc-animation-speed').value),
            pause_on_hover: document.getElementById('abc-pause-on-hover').checked,
            infinite_loop: document.getElementById('abc-infinite-loop').checked,
            show_arrows: document.getElementById('abc-show-arrows').checked,
            show_dots: document.getElementById('abc-show-dots').checked,
            slides_to_show: parseFloat(document.getElementById('abc-slides-to-show').value),
            variable_width: document.getElementById('abc-variable-width').checked
        };

        const formData = new URLSearchParams({
            action: 'abc_save_banner',
            nonce: abc_admin_vars.nonce,
            name: name,
            slug: slug,
            settings: JSON.stringify(settings),
            slides: JSON.stringify(slides)
        });

        const bannerId = bannerEditor.getAttribute('data-banner-id');
        if (bannerId) {
            formData.append('id', bannerId);
        }

        try {
            const response = await fetch(abc_admin_vars.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                alert('Banner saved successfully');
                window.location.reload();
            } else {
                alert('Error: ' + data.data);
            }
        } catch (error) {
            alert('Request failed');
            console.error('Error:', error);
        }
    }

    // Delete banner via AJAX
    async function deleteBanner(bannerId) {
        try {
            const response = await fetch(abc_admin_vars.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    action: 'abc_delete_banner',
                    nonce: abc_admin_vars.nonce,
                    id: bannerId
                })
            });

            const data = await response.json();

            if (data.success) {
                alert('Banner deleted successfully');
                window.location.reload();
            } else {
                alert('Error: ' + data.data);
            }
        } catch (error) {
            alert('Request failed');
            console.error('Error:', error);
        }
    }
});
