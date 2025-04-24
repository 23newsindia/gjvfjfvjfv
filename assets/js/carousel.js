class ABCarousel {
    constructor(element) {
        this.carousel = element;
        this.wrapper = this.carousel.querySelector('.abc-carousel-wrapper');
        this.inner = this.carousel.querySelector('.abc-carousel-inner');
        this.slides = Array.from(this.carousel.querySelectorAll('.abc-slide'));
        this.prevBtn = this.carousel.querySelector('.abc-carousel-prev');
        this.nextBtn = this.carousel.querySelector('.abc-carousel-next');
        
        // Get settings from data attribute
        this.settings = JSON.parse(this.carousel.getAttribute('data-settings'));
        
        // Current state
        this.currentIndex = 0;
        this.isAnimating = false;
        this.autoPlayInterval = null;
        this.slideCount = this.slides.length;
        
        // Touch handling properties
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.touchMoveX = 0;
        this.touchMoveY = 0;
        this.isDragging = false;
        this.startPos = 0;
        this.currentTranslate = 0;
        this.prevTranslate = 0;
        this.animationID = null;
        this.lastTouch = null;
        this.swipeThreshold = 50;
        this.isScrolling = null; // Track scroll direction
        
        // Initialize
        this.init();
    }
    
    init() {
        if (this.slides.length === 0) return;
        
        this.setupSlider();
        this.loadFirstSlide();
        this.lazyLoadImages();
        this.setupEventListeners();
        
        if (this.settings.autoplay) {
            this.startAutoPlay();
        }
    }

    loadFirstSlide() {
        const firstSlideImg = this.slides[0]?.querySelector('img');
        if (firstSlideImg) {
            firstSlideImg.loading = 'eager';
            firstSlideImg.fetchpriority = 'high';
            firstSlideImg.decoding = 'sync';
            firstSlideImg.src = firstSlideImg.dataset.src || firstSlideImg.src;
            firstSlideImg.classList.add('customFade-active');
        }
    }

    lazyLoadImages() {
        const lazyImages = this.carousel.querySelectorAll('.abc-slide-image:not(.abc-first-slide)');
        
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        this.loadImage(img);
                        observer.unobserve(img);
                    }
                });
            }, {
                root: this.wrapper,
                rootMargin: '200px 0px',
                threshold: 0.01
            });
            
            lazyImages.forEach(img => observer.observe(img));
        } else {
            lazyImages.forEach(img => this.loadImage(img));
        }
    }

    loadImage(img) {
        if (!img.dataset.src) return;
        
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
        
        img.onload = () => {
            img.classList.add('abc-image-loaded');
        };
    }

    setupSlider() {
        const isMobile = window.innerWidth <= 991;
        const slideWidth = isMobile ? '76.92308%' : 'calc(33.333333% - 10px)';
        
        this.slides.forEach(slide => {
            slide.style.width = slideWidth;
            slide.style.flexShrink = 0;
        });
        
        this.slideWidth = this.slides[0].offsetWidth;
        this.goToSlide(0, false);
    }

    setupEventListeners() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', () => this.prevSlide());
        }
        
        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', () => this.nextSlide());
        }
        
        // Touch events with passive option for better scrolling
        this.inner.addEventListener('touchstart', e => this.touchStart(e), { passive: true });
        this.inner.addEventListener('touchmove', e => this.touchMove(e), { passive: true });
        this.inner.addEventListener('touchend', e => this.touchEnd(e));
        
        // Mouse events
        this.inner.addEventListener('mousedown', e => this.touchStart(e));
        window.addEventListener('mousemove', e => this.touchMove(e));
        window.addEventListener('mouseup', e => this.touchEnd(e));
        
        // Prevent context menu on long press
        this.inner.addEventListener('contextmenu', e => e.preventDefault());
        
        if (this.settings.pause_on_hover) {
            this.carousel.addEventListener('mouseenter', () => this.pauseAutoPlay());
            this.carousel.addEventListener('mouseleave', () => {
                if (this.settings.autoplay) {
                    this.resumeAutoPlay();
                }
            });
        }
        
        window.addEventListener('resize', () => this.handleResize());
    }

    touchStart(e) {
        const touch = e.type === 'mousedown' ? e : e.touches[0];
        this.isDragging = true;
        this.startPos = touch.clientX;
        this.touchStartX = touch.clientX;
        this.touchStartY = touch.clientY;
        this.currentTranslate = this.getCurrentTranslateX();
        this.prevTranslate = this.currentTranslate;
        this.lastTouch = touch;
        this.isScrolling = null; // Reset scroll direction detection
        
        this.inner.style.transition = 'none';
        this.inner.classList.add('grabbing');
        
        if (this.animationID) {
            cancelAnimationFrame(this.animationID);
        }
        this.animationFrame();
        
        this.pauseAutoPlay();
    }

    touchMove(e) {
        if (!this.isDragging) return;

        const touch = e.type === 'mousemove' ? e : e.touches[0];
        const currentX = touch.clientX;
        const currentY = touch.clientY;
        
        // Calculate movement on both axes
        const deltaX = currentX - this.touchStartX;
        const deltaY = currentY - this.touchStartY;

        // Determine scroll direction on first move
        if (this.isScrolling === null) {
            this.isScrolling = Math.abs(deltaY) > Math.abs(deltaX);
        }

        // If scrolling vertically, don't interfere with page scroll
        if (this.isScrolling) {
            this.isDragging = false;
            return;
        }

        // Only prevent default for horizontal swipes
        if (!this.isScrolling) {
            e.preventDefault();
            this.currentTranslate = this.prevTranslate + deltaX;
        }

        this.lastTouch = touch;
    }

    touchEnd(e) {
        if (!this.isDragging) return;
        
        this.isDragging = false;
        this.inner.classList.remove('grabbing');
        
        if (this.isScrolling) {
            this.isScrolling = null;
            return;
        }

        const movedBy = this.currentTranslate - this.prevTranslate;
        
        if (Math.abs(movedBy) > this.swipeThreshold) {
            if (movedBy < 0) {
                this.nextSlide();
            } else {
                this.prevSlide();
            }
        } else {
            this.goToSlide(this.currentIndex);
        }

        if (this.settings.autoplay) {
            this.resumeAutoPlay();
        }

        cancelAnimationFrame(this.animationID);
    }

    animationFrame() {
        if (this.isDragging) {
            this.setSlidePosition();
            this.animationID = requestAnimationFrame(() => this.animationFrame());
        }
    }

    setSlidePosition() {
        this.inner.style.transform = `translateX(${this.currentTranslate}px)`;
    }

    getCurrentTranslateX() {
        const style = window.getComputedStyle(this.inner);
        const matrix = new DOMMatrix(style.transform);
        return matrix.m41;
    }

    handleResize() {
        this.setupSlider();
        this.goToSlide(this.currentIndex, false);
    }

    goToSlide(index, animate = true) {
        if (this.isAnimating) return;
        
        const totalSlides = this.slides.length;
        
        if (this.settings.infinite_loop) {
            if (index < 0) index = totalSlides - 1;
            if (index >= totalSlides) index = 0;
        } else {
            if (index < 0) index = 0;
            if (index >= totalSlides) index = totalSlides - 1;
        }
        
        this.currentIndex = index;
        const translateX = -(index * (this.slideWidth + 8));
        
        if (animate) {
            this.isAnimating = true;
            this.inner.style.transition = `transform ${this.settings.animation_speed}ms ease`;
        } else {
            this.inner.style.transition = 'none';
        }
        
        this.inner.style.transform = `translateX(${translateX}px)`;
        
        if (animate) {
            setTimeout(() => {
                this.isAnimating = false;
                this.inner.style.transition = '';
            }, this.settings.animation_speed);
        }
    }

    nextSlide() {
        this.goToSlide(this.currentIndex + 1);
    }

    prevSlide() {
        this.goToSlide(this.currentIndex - 1);
    }

    startAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
        }
        
        this.autoPlayInterval = setInterval(() => {
            if (!this.isDragging) {
                this.nextSlide();
            }
        }, this.settings.autoplay_speed);
    }

    pauseAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    resumeAutoPlay() {
        if (!this.autoPlayInterval && this.settings.autoplay) {
            this.startAutoPlay();
        }
    }

    cleanup() {
        this.pauseAutoPlay();
        
        if (this.animationID) {
            cancelAnimationFrame(this.animationID);
        }
        
        this.inner.removeEventListener('touchstart', this.touchStart);
        this.inner.removeEventListener('touchmove', this.touchMove);
        this.inner.removeEventListener('touchend', this.touchEnd);
        this.inner.removeEventListener('mousedown', this.touchStart);
        window.removeEventListener('mousemove', this.touchMove);
        window.removeEventListener('mouseup', this.touchEnd);
        
        if (this.settings.pause_on_hover) {
            this.carousel.removeEventListener('mouseenter', this.pauseAutoPlay);
            this.carousel.removeEventListener('mouseleave', this.resumeAutoPlay);
        }
        
        window.removeEventListener('resize', this.handleResize);
    }
}

// Initialize carousels
document.addEventListener('DOMContentLoaded', () => {
    const carousels = document.querySelectorAll('.abc-banner-carousel');
    window.abcCarousels = [];
    
    carousels.forEach(carousel => {
        window.abcCarousels.push(new ABCarousel(carousel));
    });
});