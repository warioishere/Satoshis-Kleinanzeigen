/**
 * Gallery image limit in the product form, driven by the vendor's package.
 */
( function () {
    const config = window.skGalleryLimit || {};
    const IMAGE_COUNT_LIMIT = config.imageCountLimit;
    const WARNING_MESSAGE = config.warningMessage;

    function updateUI() {
        const addedImages = document.querySelectorAll("#product_images_container .image").length;
        const selectedImages = document.querySelectorAll("[aria-checked='true']").length;
        const submitButton = document.querySelector('.media-toolbar button');
        const addImageButton = document.querySelector("#product_images_container .add-image");

        // Update submit button state
        if (submitButton && !['Set featured image', 'Set variation image'].includes(submitButton.innerText)) {
            submitButton.disabled = (selectedImages + addedImages > IMAGE_COUNT_LIMIT) || (selectedImages < 1);
        }

        // Update add image button visibility
        if (addImageButton) {
            addImageButton.style.display = addedImages >= IMAGE_COUNT_LIMIT ? 'none' : '';
        }

        // Show or remove warning message based on current image count
        const warningMessage = document.getElementById('sk-image-limit-warning');
        if (addedImages > IMAGE_COUNT_LIMIT && WARNING_MESSAGE) {
            if (!warningMessage) {
                const container = document.querySelector("#product_images_container");
                const warning = document.createElement('div');
                warning.id = 'sk-image-limit-warning';
                warning.className = 'sk-alert sk-alert-warning';
                warning.innerHTML = WARNING_MESSAGE;
                container.appendChild(warning);
            }
        } else if (warningMessage) {
            warningMessage.remove();
        }
    }

    function initializeMutationObserver() {
        const observer = new MutationObserver(() => {
            if (document.querySelector('.attachments-browser ul')) {
                updateUI();
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function setupImageDeletionListener() {
        const imageContainer = document.querySelector("#product_images_container");
        if (imageContainer) {
            imageContainer.addEventListener('click', (event) => {
                if (event.target.matches('.image a.action-delete')) {
                    setTimeout(updateUI, 100);
                }
            });
        }
    }

    function initializeProductImageLimitation() {
        updateUI();
        initializeMutationObserver();
        setupImageDeletionListener();
    }

    document.addEventListener('DOMContentLoaded', initializeProductImageLimitation);
} )();
