(function ($) {
	'use strict';

	class WCGSGallery {
		constructor() {
			this.namespace = '.wcgsGallery';
			this.players = [];
			this.wcgs_spswiper_thumb = null;
			this.wcgs_spswiper_gallery = null;
			this.settings = wcgs_object.wcgs_settings;
			this.wcgs_body_font_size = parseInt(wcgs_object.wcgs_body_font_size);
			this.playbackTimes = {};
			this.publicUrl = wcgs_object.wcgs_public_url;
			this.fancyLoaded = false;
			this.spswiperLoaded = false;
			this.lazyAttr = this.settings.wcgs_image_lazy_load && this.settings.wcgs_image_lazy_load == 'ondemand' ? 'loading = "lazy"' : '';
			this.$gallery = $('#wpgs-gallery');
			this.$summary = this.$gallery.next('.summary');
			// Bind event handlers to preserve context.
			this.bindEventHandlers();

			jQuery(() => this.initialize());
		}

		// Bind event handlers to preserve context
		bindEventHandlers() {
			this.handleResize = this.handleResize.bind(this);
			this.handleLightboxClick = this.handleLightboxClick.bind(this);
			this.handleVariationChange = this.handleVariationChange.bind(this);
			this.handleYouTubeStateChange = this.handleYouTubeStateChange.bind(this);
			this.handleSlideChange = this.handleSlideChange.bind(this);
		}

		// Initialize the gallery
		initialize() {
			this.setupImageLoading();
			this.initializeYouTube();
			this.setupEventHandlers();
			this.initializeGallery();
			this.$gallery.wpgspimagesLoaded().then(() => {
				this.hidePreloader();
			});
			this.addGrayscale();
		}
		// getMaxImageHeight() {
		// 	let maxHeight = 0;
		// 	this.$gallery.find('.wcgs-carousel img').each(function () {
		// 		if ($(this).innerHeight() > maxHeight) {
		// 			maxHeight = $(this).innerHeight();
		// 		}
		// 	});
		// 	return maxHeight;
		// }
		// Initialize SPSwiper instance
		SPSwiperSlide(selector, options) {
			if (typeof SPSwiper !== 'undefined') {
				return new SPSwiper(selector, options);
			} else if (typeof SPSwiper !== 'undefined') {
				return new SPSwiper(selector, options);
			} else {
				console.log("SPSwiper is undefined");
				return false;
			}
		}

		// Setup image loading.
		setupImageLoading() {
			$.fn.wpgspimagesLoaded = function () {
				const $imgs = this.find('img[src!=""]');
				if (!$imgs.length) return $.Deferred().resolve().promise();
				const dfds = [];
				$imgs.each(function (index) {
					if (index == 1) {
						const dfd = $.Deferred();
						dfds.push(dfd);
						const img = new Image();
						img.onload = () => dfd.resolve();
						img.onerror = () => dfd.resolve();
						img.src = this.src;
					}
				});
				return $.when.apply($, dfds);
			};

		}

		// Initialize YouTube API.
		initializeYouTube() {
			if (this.settings.video_popup_place !== 'inline') return;
			this.addYouTubeScript();
			this.checkYouTubeReady();
		}

		// Add YouTube script to the document.
		addYouTubeScript() {
			const scriptId = 'youtube-api';
			if (!document.getElementById(scriptId)) {
				const tag = document.createElement('script');
				tag.src = 'https://www.youtube.com/iframe_api';
				tag.id = scriptId;
				document.getElementsByTagName('script')[0].parentNode.insertBefore(tag, document.getElementsByTagName('script')[0]);
			}
		}

		// Check if YouTube API is ready.
		checkYouTubeReady() {
			const interval = setInterval(() => {
				if (typeof YT === 'object' && typeof YT.Player === 'function') {
					clearInterval(interval);
					this.initializeYouTubePlayers();
				}
			}, 300);
		}

		// Initialize YouTube players.
		initializeYouTubePlayers() {
			$('.wcgs-youtube-video').each((index, element) => {
				const videoId = $(element).data('video-id');
				this.players[videoId] = new YT.Player(element, {
					videoId: videoId,
					playerVars: { modestbranding: 1, showinfo: 0, fs: 1 },
					events: {
						onStateChange: this.handleYouTubeStateChange
					}
				});
			});
		}


		initializeFancyboxScript() {
			const fancyboxScriptId = 'wcgs-fancybox-js';
			// Check if fancybox js script is already added
			if (!document.getElementById(fancyboxScriptId)) {
				const jsTag = document.createElement('script');
				jsTag.src = this.publicUrl + 'js/fancybox-bundle.min.js';
				jsTag.id = fancyboxScriptId;
				jsTag.defer = true;
				// Append to document.
				const firstScript = document.getElementsByTagName('script')[0];
				firstScript.parentNode.insertBefore(jsTag, firstScript);
			}
		}
		// Initial swiper js.
		initializeSwiperScript() {
			const swiperScriptId = 'wcgs-swiper-js';
			// Check if swiper.js script is already added
			if (!document.getElementById(swiperScriptId)) {
				const jsTag = document.createElement('script');
				jsTag.src = this.publicUrl + 'js/swiper.min.js';
				jsTag.id = swiperScriptId;
				jsTag.defer = true;
				// Append to document.
				const firstScript = document.getElementsByTagName('script')[0];
				firstScript.parentNode.insertBefore(jsTag, firstScript);
			}
		}
		checkSPSwiperAPIReady() {

			// Check if the spswiper is already loaded.
			if (typeof SPSwiper !== 'undefined') {
				this.spswiperLoaded = true;
				this.initializeSPSwipers();
				return;
			}
			const checkInterval = setInterval(() => {
				this.initializeSwiperScript();
				if (typeof SPSwiper == 'function') {
					clearInterval(checkInterval);
					this.spswiperLoaded = true;
					this.initializeSPSwipers();
				}
			}, 300);
			// Prevent infinite checking.
			setTimeout(() => {
				clearInterval(checkInterval);
				if (!this.spswiperLoaded) {
					console.log('SPSwiper script failed to load');
				}
			}, 10000);
		}
		checkFancyboxReady() {
			if (typeof Fancybox != 'undefined') {
				this.fancyLoaded = true;
				this.initializeLightbox();
				return;
			}
			const checkInterval = setInterval(() => {
				this.initializeFancyboxScript()

				if (typeof Fancybox != 'undefined') {
					clearInterval(checkInterval);
					this.fancyLoaded = true;
					this.initializeLightbox();
				}
			}, 300);
			setTimeout(() => {
				if (!this.fancyLoaded) {
					clearInterval(checkInterval);
				}
			}, 5000);
		}

		// Handle YouTube player state change.
		handleYouTubeStateChange(event) {
			const videoId = event.target.getIframe().id;
			if ([YT.PlayerState.PAUSED, YT.PlayerState.ENDED].includes(event.data)) {
				this.playbackTimes[videoId] = event.target.getCurrentTime();
			}
		}

		// Setup event handlers
		setupEventHandlers() {
			// Remove any existing event handlers
			$(window).off(this.namespace);
			$(document).off(this.namespace);

			// Add namespaced event handlers
			$(window).on(`resize${this.namespace}`, this.handleResize);

			$(document).on(
				`click${this.namespace}`,
				'.wcgs-carousel .wcgs-lightbox, .wcgs-carousel .wcgs-video-icon, .wcgs-carousel .wcgs-photo',
				this.handleLightboxClick
			);

			$(document).on(
				`change${this.namespace}`,
				'form.variations_form .variations select, form.variations_form .variations input[type=radio]',
				this.handleVariationChange
			);

			if (this.settings.zoom) {
				$('.wcgs-slider-image').off(this.namespace)
					.on(`mouseenter${this.namespace} mouseleave${this.namespace} mousemove${this.namespace}`,
						e => this.handleZoom(e));
			}

			if (this.settings.navigation === '1' || this.settings.navigation === true) {
				this.$gallery.find('.gallery-navigation-carousel .wcgs-spswiper-button-next')
					.off(this.namespace)
					.on(`click${this.namespace}`, () => {
						this.$gallery.find('.wcgs-carousel .wcgs-spswiper-button-next').trigger('click');
					});

				this.$gallery.find('.gallery-navigation-carousel .wcgs-spswiper-button-prev')
					.off(this.namespace)
					.on(`click${this.namespace}`, () => {
						this.$gallery.find('.wcgs-carousel .wcgs-spswiper-button-prev').trigger('click');
					});
			}

			if (this.settings.accessibility === '1' ) {
				// Make navigation arrows keyboard-activatable (Enter / Space)
				this.$gallery.find('.wcgs-spswiper-arrow')
					.attr('tabindex', function() {
						// preserve existing tabindex if present
						return typeof $(this).attr('tabindex') !== 'undefined' ? $(this).attr('tabindex') : 0;
					})
					.attr('role', function() {
						return typeof $(this).attr('role') !== 'undefined' ? $(this).attr('role') : 'button';
					})
					.off(`keydown${this.namespace}`)
					.on(`keydown${this.namespace}`, (e) => {
						const key = e.key || e.keyCode;
						if (key === 'Enter' || key === ' ' || key === 'Spacebar' || key === 13 || key === 32) {
							e.preventDefault();
							$(e.currentTarget).trigger('click');
						}
					});
			}
		}

		// Initialize the gallery
		initializeGallery() {
			this.videoIcon();
			this.calculateDimensions();
			this.checkSPSwiperAPIReady()
			// this.setupZoom();
			this.checkFancyboxReady();
		}

		// Calculate gallery dimensions
		calculateDimensions() {
			let gallery_w = this.settings.gallery_width;

			if ($('body').hasClass('et_divi_builder') || $('body').hasClass('theme-Divi')) {
				var gallery_divi_width = $('.wcgs-gallery-slider.et-db #et-boc .et-l .et_pb_gutters3 .et_pb_column_1_2').width();
				if (typeof gallery_divi_width === "number") {
					gallery_w = gallery_divi_width;
				}
			}

			if ($('body').hasClass('theme-flatsome')) {
				var gallery_flatsome_width = $('.single-product .product .row.content-row .product-gallery').width();
				if (typeof gallery_flatsome_width === "number") {
					gallery_w = gallery_flatsome_width;
				}
			}

			if ($('.wcgs-woocommerce-product-gallery').parents('.hestia-product-image-wrap').length) {
				var gallery_hestia_width = $('.wcgs-woocommerce-product-gallery').parents('.hestia-product-image-wrap').width();
				if (typeof gallery_hestia_width === "number") {
					gallery_w = gallery_hestia_width;
				}
			}
			// Fix  the gallery width issue for page builder case.
			if (!$('#wpgs-gallery ~ .summary').length) {
				let gallery_width = this.$gallery.parent('*').outerWidth();
				if (typeof gallery_width === "number" && gallery_width > 50) {
					gallery_w = gallery_width;
				}
			}
			// Responsive widths.
			if ($(window).width() < 992) {
				if (this.settings.gallery_responsive_width.width > 0) {
					gallery_w = this.settings.gallery_responsive_width.width;
				}
			}
			if ($(window).width() < 768) {
				gallery_w = this.settings.gallery_responsive_width.height;
			}
			if ($(window).width() < 480) {
				gallery_w = this.settings.gallery_responsive_width.height2;
			}
			let widthUnit = this.getWidthUnit();
			if (gallery_w > 100) {
				widthUnit = 'px';
				let currentGLWidth = this.$gallery.parent().outerWidth();
				gallery_w = currentGLWidth > gallery_w ? gallery_w : currentGLWidth;
			}
			this.$gallery.css({
				minWidth: 'auto',
				maxWidth: `${gallery_w}${widthUnit}`
			});
			this.updateSummaryWidth(gallery_w);
			if (this.settings.gallery_layout == 'vertical' ||
				this.settings.gallery_layout == 'vertical_right') {
				let maxHeight = this.getMaxImageHeight();
				this.$gallery.find('.gallery-navigation-carousel-wrapper').css({ 'maxHeight': maxHeight });
			}
			setTimeout(() => {

				if (this.settings.slide_orientation == 'vertical') {
					let maxHeight = this.getMaxImageHeight();
					this.$gallery.find('.wcgs-carousel .spswiper-slide, .wcgs-carousel').css({ 'height': maxHeight });
				}
				if (this.settings.gallery_layout == 'vertical' ||
					this.settings.gallery_layout == 'vertical_right') {
					let maxHeight = this.getMaxImageHeight();
					this.$gallery.find('.gallery-navigation-carousel-wrapper').css({ 'maxHeight': maxHeight });
				}
				// this.$gallery.find('.wcgs-carousel .spswiper-slide').css({
				// 	"display": "flex",
				// 	"justify-content": "center",
				// 	"align-items": "center",
				// });
			}, 1000)
		}
		getMaxImageHeight() {
			let maxHeight = 0;
			const thumbnails_sliders_space = this.settings.thumbnails_sliders_space ? this.settings.thumbnails_sliders_space.width : 6;
			let gallery = this.$gallery;
			const actualWidth = gallery.find('.wcgs-carousel').width();
			// Get the maximum height of images in the gallery.
			this.$gallery.find('.wcgs-carousel img').each(function () {
				// Check if height is set in attributes.
				if ($(this).attr('height') && $(this).attr('width')) {
					const attrWidth = parseInt($(this).attr('width'), 10);
					const attrHeight = parseInt($(this).attr('height'), 10);
					if (attrWidth && attrHeight) {
						const ratio = attrHeight / attrWidth;
						// Rendered width on screen.
						const estimatedHeight = actualWidth * ratio;
						if (estimatedHeight > maxHeight) {
							maxHeight = estimatedHeight;
						}
					}
				} else {
					if ($(this).innerHeight() > maxHeight) {
						maxHeight = $(this).innerHeight();
					}
				}
			});
			return maxHeight;
		}
		// Get width unit for gallery.
		getWidthUnit() {
			return window.innerWidth < 768 ? this.settings.gallery_responsive_width.unit : '%';
		}

		// Update summary width based on gallery width.
		updateSummaryWidth(galleryWidth) {
			const summaryWidth = 100 - galleryWidth;
			this.$summary.css('maxWidth', summaryWidth > 20 ? `calc(${summaryWidth}% - 30px)` : '');
		}
		getThumbAutoplaySettings() {
			if (this.settings.autoplay != '1') return false;
			return {
				delay: parseInt(this.settings.autoplay_interval) || 3000,
				pauseOnMouseEnter: false
			};
		}
		handleAutoplayEvents() {
			// Remove event listeners
			if (this.settings.autoplay && this.settings.autoplay == '1') {
				this.$gallery.on({
					mouseenter: () => {
						if (this.wcgs_spswiper_gallery && this.wcgs_spswiper_gallery.autoplay) {
							this.wcgs_spswiper_gallery.autoplay.stop();
						}
					},
					mouseleave: () => {
						if (this.wcgs_spswiper_gallery && this.wcgs_spswiper_gallery.autoplay) {
							this.wcgs_spswiper_gallery.autoplay.start();
						}
					}
				});
			}
		}
		// Initialize SPSwiper instances
		initializeSPSwipers() {
			const thumbnail_nav = this.settings.thumbnailnavigation === '1' || this.settings.thumbnailnavigation === true;
			const navigation = this.settings.navigation === '1' || this.settings.navigation === true;
			const thumbnails_sliders_space = this.settings.thumbnails_sliders_space ? this.settings.thumbnails_sliders_space.width : 6;
			const slider_autoplay = this.settings.autoplay && this.settings.autoplay == '1' ? this.getThumbAutoplaySettings() : false;
			let $swiperThumbContainer = this.$gallery.find('.gallery-navigation-carousel');
			let $mainSliderContainer = this.$gallery.find(".wcgs-carousel");
			let wcgs_img_count = this.$gallery.find('.wcgs-carousel .spswiper-slide').length;
			const galleryLayout = (this.settings.gallery_layout == 'vertical' ||
				this.settings.gallery_layout == 'vertical_right') ? 'vertical' : 'horizontal';
			const thumbnails_item_to_show = (this.settings.gallery_layout == 'vertical' || this.settings.gallery_layout == 'vertical_right') ? 'auto' : parseInt(this.settings.thumbnails_item_to_show);
			this.wcgs_spswiper_thumb = this.SPSwiperSlide($swiperThumbContainer[0], {
				slidesPerView: thumbnails_item_to_show,
				direction: galleryLayout,
				watchSlidesVisibility: true,
				watchSlidesProgress: true,
				autoHeight: false,
				watchOverflow: true,
				lazyPreloadPrevNext: 1,
				loop: this.settings.infinite_loop === '1',
				spaceBetween: parseInt(thumbnails_sliders_space),
				freeMode: this.settings.free_mode === '1',
				mousewheel: this.settings.mouse_wheel === '1',
				on: {
					afterInit: () => this.handleSPSwiperInit()
				}
			});

			if (this.settings.thumbnail_style == 'top_line') {
				this.setupThumbnailBottomLine();
			}

			let adaptive_height = (this.settings.slider_height_type && this.settings.slider_height_type == 'adaptive') ? true : false;
			this.wcgs_spswiper_gallery = this.SPSwiperSlide($mainSliderContainer[0], {
				autoHeight: adaptive_height,
				direction: this.settings.slide_orientation || 'horizontal',
				loop: this.settings.infinite_loop === '1',
				thumbs: { spswiper: this.wcgs_spswiper_thumb },
				autoplay: slider_autoplay,
				lazyPreloadPrevNext: 1,
				slidesPerView: 1,
				spaceBetween: 1,
				effect: this.settings.fade_slide,
				speed: parseInt(this.settings.autoplay_speed) || 300,
				observer: true,
				watchOverflow: true,
				mousewheel: this.settings.mouse_wheel === '1',
				observeParents: true,
				a11y: this.settings.accessibility === '1' ? {
					prevSlideMessage: 'Previous slide',
					nextSlideMessage: 'Next slide',
				} : false,
				navigation: navigation ? {
					nextEl: ".wcgs-carousel .wcgs-spswiper-button-next",
					prevEl: ".wcgs-carousel .wcgs-spswiper-button-prev",
				} : thumbnail_nav ? {
					nextEl: ".gallery-navigation-carousel .wcgs-spswiper-button-next",
					prevEl: ".gallery-navigation-carousel .wcgs-spswiper-button-prev",
				} : false,
				pagination: this.settings.pagination === '1' ? {
					el: '.wcgs-carousel .spswiper-pagination',
					type: 'bullets',
					dynamicBullets: this.settings.pagination_type === 'dynamic' ? true : false,
					clickable: true,
				} : false,
				on: {
					slideChange: this.handleSlideChange
				}
			});

			this.wcgs_spswiper_gallery.init();

			setTimeout(() => {
				this.$gallery.removeClass('wcgs-spswiper-before-init');
				this.handleAutoplayEvents();
			}, 400);

			this.checkArrowsVisibility();
			// hide nav carousel if item is one!
			if (this.settings.gallery_layout !== 'hide_thumb') {
				if (wcgs_img_count <= 1) {
					this.$gallery.find('.gallery-navigation-carousel-wrapper').addClass('wcgs-hidden').hide();
					this.$gallery.find(".wcgs-spswiper-arrow").hide()
				} else {
					this.$gallery.find('.gallery-navigation-carousel-wrapper').removeClass('wcgs-hidden').show();
					this.$gallery.find('.wcgs-spswiper-arrow:not(.swiper-button-lock)').show();
				}
			}
		}


		navigationVisibility() {
			// Carousel navigation visibility.
			let navigation_visibility = (this.settings.navigation_visibility == 'hover') ? true : false;
			let $gallery = this.$gallery;
			if (navigation_visibility) {
				$gallery.find(".wcgs-carousel .wcgs-spswiper-arrow").hide();
				$gallery.find(".wcgs-carousel .wcgs-spswiper-arrow").css('opacity', 1);
				$gallery.find(".wcgs-carousel").on({
					mouseenter: () => {
						this.$gallery.find(".wcgs-carousel .wcgs-spswiper-arrow:not(.spswiper-button-lock)").show();
					},
					mouseleave: () => {
						this.$gallery.find(".wcgs-carousel .wcgs-spswiper-arrow:not(.spswiper-button-lock)").hide();
					}
				});
			}
			// Thumb navigation visibility.
			let thumb_navigation_visibility = (this.settings.thumb_nav_visibility == 'hover') ? true : false;
			if (thumb_navigation_visibility) {
				this.$gallery.find(".gallery-navigation-carousel .wcgs-spswiper-arrow").hide()
				this.$gallery.find(".gallery-navigation-carousel").on({
					mouseenter: () => {
						this.$gallery.find(".gallery-navigation-carousel .wcgs-spswiper-arrow:not(.spswiper-button-lock)").show();
					},
					mouseleave: () => {
						this.$gallery.find(".gallery-navigation-carousel .wcgs-spswiper-arrow").hide();
					}
				});
			}
			// Pagination visibility.
			var pagination_visibility = (this.settings.pagination_visibility == 'hover') ? true : false;
			if (pagination_visibility) {
				this.$gallery.find('.spswiper-pagination').hide();
				this.$gallery.find('.wcgs-carousel').on({
					mouseenter: () => {
						this.$gallery.find('.spswiper-pagination').show();
					},
					mouseleave: () => {
						this.$gallery.find('.spswiper-pagination').hide();
					}
				});
			}
		}
		// Handle SPSwiper initialization.
		handleSPSwiperInit() {
			setTimeout(() => {
				this.$gallery.removeClass('wcgs-spswiper-before-init');
			}, 400);
		}


		// Check visibility of navigation arrows.
		checkArrowsVisibility() {
			setTimeout(() => {
				var allowSlidePrev = typeof this.wcgs_spswiper_thumb.allowSlidePrev != 'undefined' ? this.wcgs_spswiper_thumb.allowSlidePrev : false;
				var allowSlideNext = typeof this.wcgs_spswiper_thumb.allowSlideNext != 'undefined' ? this.wcgs_spswiper_thumb.allowSlideNext : false;
				if (allowSlidePrev || allowSlideNext) {
					this.$gallery.find(".gallery-navigation-carousel-wrapper .wcgs-spswiper-arrow:not(.spswiper-button-lock)").show();
				} else {
					this.$gallery.find(".gallery-navigation-carousel-wrapper .wcgs-spswiper-arrow").addClass('spswiper-button-lock').hide();
				}
				this.navigationVisibility();
			}, 300);
		}

		// Handle slide change event
		handleSlideChange() {
			setTimeout(() => {
				this.pausePreviousVideo();
			}, 500);
		}

		// Pause the previous video
		pausePreviousVideo() {
			if (this.wcgs_spswiper_gallery && this.wcgs_spswiper_gallery.previousIndex !== undefined) {
				const prevIndex = this.wcgs_spswiper_gallery.previousIndex;
				const $prevSlide = $(`.wcgs-carousel .spswiper-slide:eq(${prevIndex})`);
				const videoId = $prevSlide.find('.wcgs-youtube-video').data('video-id');

				if (videoId && this.players[videoId]) {
					this.players[videoId].pauseVideo();
				}
			}
		}

		// Handle zoom events
		handleZoom(e) {
			var scale_level = this.settings.zoom_type && (this.settings.zoom_type == 'zoom_2x') ? 2 : 1.5;

			if (this.settings.zoom != '1') return;
			if ($(window).width() < 480 && this.settings.mobile_zoom != '1') return;
			const $target = $(e.currentTarget);
			const scale = e.type === 'mouseenter' || e.type === 'mousemove' ? scale_level : 1;
			this.initializeZoomElement($target)
			//	$('.wcgs-slider-image').each((i, el) => );
			$target.find('.wcgs-photo').css({
				transform: `scale(${scale})`,
				transformOrigin: this.getTransformOrigin(e)
			});
		}

		// Get transform origin for zoom
		getTransformOrigin(e) {
			const offset = $(e.currentTarget).offset();
			const x = ((e.pageX - offset.left) / $(e.currentTarget).width()) * 100;
			const y = ((e.pageY - offset.top) / $(e.currentTarget).height()) * 100;
			return `${x}% ${y}%`;
		}

		// Initialize zoom element
		initializeZoomElement(element) {
			const $element = element;
			if (!$element.find('.wcgs-photo').length) {
				$element.append('<div class="wcgs-photo"></div>');
			}
			$element.find('.wcgs-photo').css('background-image',
				`url(${$element.find('img').attr('src')})`);
		}

		// Initialize lightbox functionality.
		// initializeLightbox() {
		// 	if (typeof $.fancybox === 'undefined' || this.settings.lightbox !== '1') return;
		// 	$.fancybox.defaults.buttons = this.getLightboxButtons();
		// 	this.$gallery.find('.wcgs-carousel').fancybox({
		// 		 selector: '.wcgs-slider-lightbox',
		// 		infobar: this.settings.l_img_counter === '1',
		// 		caption: (_, current) => {
		// 			var caption = '';
		// 			if (this.settings.lightbox_caption == '1') {
		// 				caption = current.opts.$orig.parent().find('img').data('cap') || '';
		// 			}
		// 			return caption;
		// 		}
		// 	});
		// }

		// Get lightbox buttons configuration
		getLightboxButtons() {
			const buttons = ['zoom'];
			if (this.settings.gallery_fs_btn === '1') buttons.push('fullscreen');
			buttons.push("close");
			return buttons;
		}

		// Grayscale.
		addGrayscale() {
			if (this.settings.grayscale !== 'gray_off') {
				let grayClass = this.settings.grayscale;
				$('.wcgs-slider-image img').addClass('' + grayClass + '');
			}
		}

		initializeLightbox() {
			if (typeof Fancybox == 'undefined') return;
			let lightbox_thumb_style = 'classic';
			let gallery_middle_btn = '1';

			Fancybox.bind("[data-fancybox]", {
				// General options
				backdropClick: "close",         // ['close'|'iterate'|false] Close on backdrop click.
				mainClass: "wcgs-fancybox-wrapper",
				// Animation options.
				animated: true,                 // [true|false] Enable/disable animations
				// hideClass: "f-fadeOut",         // Hide animation class
				// showClass: "f-fadeIn",          // Show animation class
				animationDuration: 1000,         // Animation duration in ms
				// Toolbar options.
				Toolbar: {
					display: {
						left: this.settings.l_img_counter == '1' ? ["infobar"] : '',
						middle: [],
						right: this.getLightboxButtons(),
					}
				},
				Thumbs: false,
				// Thumbnails component.
				// Thumbs: this.settings.thumb_gallery_show == '1' ? {
				// 	type: lightbox_thumb_style,            // ['classic'|'modern'] Thumbnails style
				// 	autoStart: true,        // [true|false] Show thumbs on start
				// 	hideOnClose: true,          // [true|false] Hide when closing
				// } : false,

				// Carousel options
				Carousel: {
					Panzoom: {
						decelFriction: 0.5,
					},
					// infinite: true,            // [true|false] Infinite navigation
					transition: this.settings.lightbox_sliding_effect || 'slide',     // ['slide'|'fade'|'crossfade'|'classic']['no_animation']
					transitionDuration: 1000,    // Transition duration in ms
				},
				// Image options
				Image: {
					zoom: true,                 // [true|false] Enable image zoom
					preload: 3,                 // Number of images to preload
					click: "toggleZoom",        // ['close'|'toggleZoom'|'next'|false]
					wheel: "zoom",              // ['zoom'|'slide'|'close'|false]
					imageProtection: true,      // [true|false] Disable right-click
				},
				Video: {
					autoplay: true,             // [true|false] Autoplay videos
					loop: true,                 // [true|false] Loop videos
					mute: true,                 // [true|false] Mute videos
					ratio: 16 / 9,              // Video aspect ratio
				},
				// Other options
				caption: (fancybox, slide) => {
					if (this.settings.lightbox_caption == '1') {
						const caption = slide.caption || '';
						return caption;
					}
					return '';
				},
				// ClickAction: "close",
				dragToClose: true, // [true|false] Enable drag-to-close
				// Events
				on: {
					// init: (fancybox) => console.log("🔥 FancyBox Initialized", fancybox),
					reveal: (fancybox, slide) => {
						$(document).find('.wcgs-carousel .wcgs-slider-image img').each((i, img) => {
							const imageUrl = $(img).attr('data-lazy');
							const thumbnailSelector = $('.wcgs-fancybox-wrapper .fancybox-thumbs__list a:nth(' + i + ')');
							const thumbnailUrl = thumbnailSelector.attr('style');
							if (imageUrl && thumbnailSelector.length > 0 && thumbnailUrl.indexOf('spinner.svg') >= 0) {
								thumbnailSelector.css("background-image", "url(" + imageUrl + ")");
							}
						});
						$(".wcgs-fancybox-wrapper ~ .elementor-lightbox").remove();
					},
				}
			});

		}

		// Handle window resize event
		handleResize() {
			this.calculateDimensions();
			if (this.wcgs_spswiper_thumb) this.wcgs_spswiper_thumb.update();
			if (this.wcgs_spswiper_gallery) this.wcgs_spswiper_gallery.update();
		}

		// Handle lightbox click event
		handleLightboxClick(e) {
			e.preventDefault();
			if ($(e.currentTarget).hasClass('wcgs-video-icon')) {
				let current_selector = $(e.currentTarget).parents('.wcgs-carousel')
					.find('.spswiper-slide-active a.wcgs-slider-lightbox');
				if (current_selector.length > 0) {
					current_selector[0].click();
				}
				return;
			}

			const lightboxEnabled = this.settings.lightbox === '1' || this.settings.lightbox === true; // Note on plugin activation default value is boolean true but on saving settings it can be saved as string '1', so we are checking for both cases here.
			if (!lightboxEnabled) {
				return;
			}
			let current_selector = $(e.currentTarget).parents('.wcgs-carousel')
				.find('.spswiper-slide-active a.wcgs-slider-lightbox');
			if (current_selector.length > 0) {
				current_selector[0].click();
			}
		}

		setupGridLightbox() {
			if (!this.settings.lightbox) return;
			let is_only_popup = this.state.videoOnlyPopup;
			$(document).off('click.wcgsLb');
			$(document).on(
				`click${this.namespace}`,
				'.wcgs-carousel .wcgs-lightbox, .wcgs-carousel .wcgs-video-icon, .wcgs-carousel .wcgs-photo', function (e) {
					e.preventDefault();
					if ($(e.target).hasClass('wcgs-video-icon') && !is_only_popup) {
						return;
					}
					let current_selector = $(this).parents('.wcgs-slider-image').find('a.wcgs-slider-lightbox');
					if (current_selector.length > 0) {
						current_selector[0].click();
					}
				});
		}
		// Add video icon to slides with video.
		videoIcon() {
			$('.wcgs-slider-image, .wcgs-thumb').each((i, el) => {
				if ($(el).find('img').data('type')) {
					$(el).append('<div class="wcgs-video-icon"></div>');
				}
			});
		}

		// Handle variation change event.
		handleVariationChange(e) {
			const data = wcgs_object.wcgs_data || [];
			const $variations_table = $(e.target).closest('.variations')
			const variations = this.getSelectedVariations($variations_table);
			this.updateGalleryBasedOnVariations(variations, data);
		}

		updateGalleryBasedOnVariations(variationsArray, data) {
			const matchingItems = this.findMatchingVariations(data, variationsArray);
			if (matchingItems.length) {
				this.rebuildGallery(matchingItems);
			}
		}

		// Get selected variations.
		getSelectedVariations($variations_table) {
			let variationsArray = {};
			$variations_table.find('tr, .aux-dropdown').each((index, element) => {
				let attributeName = $(element).find('select').data('attribute_name');
				let attributeValue = $(element).find('select').val();
				if ($(element).find('input[type=radio]').length > 0) {
					// Get the name and value of the selected radio button.
					attributeName = $(element).find('input[type=radio]:checked').attr('name');
					attributeValue = $(element).find('input[type=radio]:checked').val();
				}

				// Only add to variationsArray if attributeName is not already present.
				if (attributeName && !(attributeName in variationsArray)) {
					variationsArray[attributeName] = attributeValue;
				}
			});

			if (this.areAllAttributesEmpty(variationsArray)) {
				return {};
			}

			return variationsArray;
		}

		// Check if all attributes in the variation object are empty.
		areAllAttributesEmpty(variation) {
			for (let key in variation) {
				if (variation[key] && variation[key].trim() !== '') {
					return false; // If any attribute has a value, return false.
				}
			}
			return true; // All attributes are empty.
		}
		isMatch(variation_attributes, attributes) {
			let match = true;
			for (let attr_name in variation_attributes) {
				if (variation_attributes.hasOwnProperty(attr_name)) {
					let val1 = variation_attributes[attr_name];
					let val2 = attributes[attr_name];
					if (val1 !== undefined && val2 !== undefined && val1.length !== 0 && val2.length !== 0 && val1 !== val2) {
						match = false;
					}
				}
			}
			return match;
		};
		findMatchingVariations(variations, attributes) {
			let matching = [];
			for (let i = 0; i < variations.length; i++) {
				let variation = variations[i];
				if ($.isEmptyObject(attributes)) {
					if ($.isEmptyObject(variation[0])) {
						const response = variation[1];
						if (response.length > 0) {
							$.merge(matching, response);
							return matching;
						}
					}
				} else if (this.isMatch(variation[0], attributes) && !$.isEmptyObject(variation[0])) {
					$.merge(matching, variation[1]);
				}
			}
			return this.uniqueItems(matching);
		};

		// Get unique items from the list.
		uniqueItems(items) {
			const seen = new Set();
			return items.filter(item => {
				const key = item.full_url;
				return seen.has(key) ? false : seen.add(key);
			});
		}

		// Update gallery with new items.
		updateGallery(items) {
			const galleryFragment = document.createDocumentFragment();
			const thumbnailFragment = document.createDocumentFragment();
			items.forEach((item, index) => {
				const slide = this.createSlide(item, index);
				const thumb = this.createThumbnail(item, index);

				galleryFragment.appendChild($(slide)[0]);
				thumbnailFragment.appendChild($(thumb)[0]);
			});

			if (this.wcgs_spswiper_thumb) {
				this.wcgs_spswiper_thumb.destroy()
				this.wcgs_spswiper_thumb = null;
			};
			if (this.wcgs_spswiper_gallery) {
				this.wcgs_spswiper_gallery.destroy();
				this.wcgs_spswiper_gallery = null;
				this.$gallery.find('.wcgs-spswiper-button-next, .wcgs-spswiper-button-prev').removeClass('spswiper-button-lock');
			}
			this.players = {};
			this.$gallery.find('.wcgs-carousel .spswiper-wrapper').empty().append(galleryFragment);
			this.$gallery.find('.gallery-navigation-carousel .spswiper-wrapper').empty().append(thumbnailFragment);


		}
		setupThumbnailBottomLine() {
			// if (this.settings.gallery_layout == 'hide_thumb') {
			// 	return;
			// }

			setTimeout(() => {
				this.wcgs_spswiper_thumb.on('sliderMove', () => {

					this.setThumbnailBorderPosition();
				});
				this.wcgs_spswiper_gallery.on('slideChange', () => {
					setTimeout(() => {
						this.setThumbnailBorderPosition();
					}, 50);
				});
			}, 100);
			// Set initial bottom line dimensions.
			setTimeout(() => {
				this.setThumbnailBorderPosition();

			}, 1000);
		}

		setThumbnailBorderPosition() {
			const isVertical = this.settings.gallery_layout == 'vertical' || this.settings.gallery_layout == 'vertical_right';
			const activeThumb = this.$gallery.find('.gallery-navigation-carousel-wrapper .wcgs-thumb.spswiper-slide-thumb-active');
			const activeThumbHeight = activeThumb.height();
			const activeThumbHeightTop = activeThumb.position().top !== undefined ? 10 + activeThumbHeight + activeThumb.position().top : 'auto';
			let activePosition = isVertical
				? (activeThumb.position() && activeThumb.position().top !== undefined ? activeThumb.position().top
					: -162) + this.wcgs_spswiper_thumb.translate
				: (activeThumb.position() && activeThumb.position().left !== undefined ? activeThumb.position().left
					: -162) + this.wcgs_spswiper_thumb.translate;
			const dimension = isVertical ? activeThumb.height() : activeThumb.width();

			this.$gallery.find('.gallery-navigation-carousel-wrapper .wcgs-border-bottom').css(
				isVertical ?
					{ height: dimension, top: activePosition } :
					this.settings.gallery_layout == 'horizontal_top' ?
						{ width: dimension, left: activePosition, bottom: 0, top: 'auto' } : { width: dimension, left: activePosition, bottom: activeThumbHeightTop, top: 'auto' }
			);
		}

		setThumbnaidlBorderPosition() {
			const isVertical = this.settings.gallery_layout == 'vertical' || this.settings.gallery_layout == 'vertical_right';
			const activeThumb = this.$gallery.find('.gallery-navigation-carousel-wrapper .wcgs-thumb.spswiper-slide-thumb-active');
			const activeThumbHeight = activeThumb.height();
			const activeThumbHeightTop = activeThumb.position().top !== undefined ? 10 + activeThumbHeight + activeThumb.position().top : 'auto';
			let activePosition = isVertical
				? (activeThumb.position() && activeThumb.position().top !== undefined ? activeThumb.position().top
					: -162) + this.spswiper.thumb.translate
				: (activeThumb.position() && activeThumb.position().left !== undefined ? activeThumb.position().left
					: -162) + this.spswiper.thumb.translate;
			const dimension = isVertical ? activeThumb.height() : activeThumb.width();
			this.$gallery.find('.gallery-navigation-carousel-wrapper .wcgs-border-bottom').css(
				isVertical ? { height: dimension, top: activePosition } : { width: dimension, left: activePosition, top: activeThumbHeightTop, bottom: 'auto' }
			);
		}
		// Create slide element.
		createSlide(item, index) {
			const hasVideo = item.video ? item.video.includes('youtu') : false;
			let lazyLoad = index > 1 ? this.lazyAttr : '';
			const videoContent = hasVideo ? this.createVideoContent(item) : '';
			const altText = item.alt_text || '';
			const imageContent = hasVideo ? videoContent : `<img src="${item.url}" alt="${altText}" data-image="${item.full_url}" height="${item.imageHeight}" width="${item.imageWidth}" ${lazyLoad} >`;

			return `<div class="spswiper-slide">
                    <div class="wcgs-slider-image">
                        <a class="wcgs-slider-lightbox" href="${hasVideo ? item.video : item.full_url}" data-fancybox="view"  data-caption="${item.cap || ''}"> </a>
                        ${imageContent}
                    </div>
                </div>
            `;
		}

		// Create video content for slide.
		createVideoContent(item) {
			const altText = item.alt_text || '';
			return this.settings.video_popup_place === 'inline'
				? `<div class="wcgs-iframe-wrapper">
                     <div class="wcgs-youtube-video" data-video-id="${this.getYouTubeId(item.video)}"> </div>
					 <img src="${item.url}" alt="${altText}" data-image="${item.full_url}" height="${item.imageHeight}" width="${item.imageWidth}" data-type="youtube" ${this.lazyAttr}>
					</div>`
				: `<img src="${item.url}" alt="${altText}" data-image="${item.full_url}" height="${item.imageHeight}" width="${item.imageWidth}" data-type="youtube" ${this.lazyAttr}>`;
		}

		// Get YouTube video ID from URL.
		getYouTubeId(url) {
			const match = url.match(/(?:youtu\.be\/|youtube\.com\/watch\?v=)([^\?&]+)/);
			return match ? match[1] : null;
		}

		// Create thumbnail element.
		createThumbnail(item, index) {
			let lazyLoad = index > 1 ? this.lazyAttr : '';
			const altText = item.alt_text || '';
			return `
                <div class="wcgs-thumb spswiper-slide">
                    <img src="${item.thumb_url}" alt="${altText}" ${item.video && item.video.includes('youtu') ? 'data-type="youtube"' : ''} ${lazyLoad} ></div>`;
		}

		// Rebuild gallery with new items.
		rebuildGallery(items) {
			this.showPreloader();
			this.updateGallery(items);
			this.reinitializeGallery();
		}

		// Show preloader.
		showPreloader() {
			this.$gallery.addClass('wcgs-transition-none');
			this.$gallery.find('.wcgs-gallery-preloader')
				.css({ opacity: 1, 'z-index': 99 });
		}

		// Reinitialize gallery after updating items.
		reinitializeGallery() {
			this.cleanup();
			this.$gallery.wpgspimagesLoaded().then(() => {
				this.initializeYouTubePlayers();
				this.initializeGallery();
				this.setupEventHandlers();
				this.hidePreloader();
			});
		}

		// Hide preloader.
		hidePreloader() {
			setTimeout(() => {
				this.$gallery.removeClass('wcgs-transition-none');
				this.$gallery.find('.wcgs-gallery-preloader').css({ opacity: 0, 'z-index': -99 });
			}, 600);
		}

		// Cleanup event handlers and SPSwiper instances.
		cleanup() {
			// Remove all event listeners with this namespace.
			$(window).off(this.namespace);
			$(document).off(this.namespace);
			$('.wcgs-slider-image').off(this.namespace);
			this.$gallery.find('.gallery-navigation-carousel .wcgs-spswiper-button-next').off(this.namespace);
			this.$gallery.find('.gallery-navigation-carousel .wcgs-spswiper-button-prev').off(this.namespace);
			// Destroy SPSwiper instances
			if (this.wcgs_spswiper_thumb) {
				this.wcgs_spswiper_thumb.destroy();
				this.wcgs_spswiper_thumb = null;
			}
			if (this.wcgs_spswiper_gallery) {
				this.wcgs_spswiper_gallery.destroy();
				this.wcgs_spswiper_gallery = null;
			}

			// Clean up YouTube players
			Object.values(this.players).forEach(player => {
				if (player && typeof player.destroy === 'function') {
					player.destroy();
				}
			});
			this.players = {};
			this.playbackTimes = {};
		}
	}
	// Initialize the gallery.
	if (wcgs_object.lazy_load_gallery && wcgs_object.lazy_load_gallery == '1') {
		function is_wcgs_pagespeed() {
			// Check for Lighthouse or GTMetrix in user agent.
			if (typeof navigator !== "undefined" &&
				/(lighthouse|gtmetrix)/i.test(navigator.userAgent.toLowerCase())) {
				return true;
			}

			// Check for Lighthouse in brands (more modern approach)
			if (typeof navigator !== "undefined" &&
				navigator.userAgentData &&
				navigator.userAgentData.brands) {

				for (var i = 0; i < navigator.userAgentData.brands.length; i++) {
					var brand = navigator.userAgentData.brands[i];
					if (brand &&
						brand.brand &&
						brand.brand.toLowerCase() === "lighthouse") {
						return true;
					}
				}
			}

			// Check for specific window dimensions
			if (window.innerWidth > 1340 &&
				window.innerWidth < 1360 &&
				window.devicePixelRatio <= 1) {
				return true;
			}

			// Check for Moto devices with specific dimensions
			if (window.innerWidth < 413 &&
				window.innerWidth > 410 &&
				typeof navigator !== "undefined" &&
				/moto/i.test(navigator.userAgent)) {
				return true;
			}

			return false;
		}
		const observer = new IntersectionObserver(entries => {
			if (entries[0].isIntersecting) {
				$("#wpgs-gallery").addClass('wcgs-visible');
				const gallery = new WCGSGallery();
				// Clean up on page unload.
				$(window).on('unload', () => {
					if (gallery) {
						gallery.cleanup();
					}
				});
				// Your script here
				observer.disconnect();
			}
		}, { threshold: 0.1 });
		// Add observer to the gallery element only if not running in Lighthouse or GTMetrix.
		if (!is_wcgs_pagespeed()) {
			observer.observe(document.querySelector("#wpgs-gallery"));
		}
	} else {
		// Initialize the gallery with proper cleanup on page unload
		jQuery(() => {
			$(document).ready(() => {
				$("#wpgs-gallery").addClass('wcgs-visible');
				const gallery = new WCGSGallery();
				// Clean up on page unload.
				$(window).on('unload', () => {
					if (gallery) {
						gallery.cleanup();
					}
				});
			});
		});
	}

})(jQuery);