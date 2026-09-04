/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import type { BlockAttributes } from '@wordpress/blocks';
import { useWooBlockProps } from '@woocommerce/block-templates';
import { __experimentalUseProductEntityProp as useProductEntityProp } from '@woocommerce/product-editor';
import { createElement, useEffect, useState } from '@wordpress/element';
import { MediaUpload } from '@wordpress/media-utils';
import { Flex, Button, Icon } from '@wordpress/components';
import { uniqBy } from 'lodash';
import { DndContext, closestCenter, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import {
	SortableContext,
	useSortable,
	arrayMove,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

function SortableImage({ image, index, onRemove }) {
	const { attributes, listeners, setNodeRef, transform, transition } = useSortable({ id: image.id });
	const style = {
		transform: CSS.Transform.toString(transform),
		transition,
	};
	return (
		<div ref={setNodeRef} style={style} className="wcgs-image" {...attributes} {...listeners}>
			<img src={image.url} alt='' />
			<div className="wcgs-image-remover" onClick={() => onRemove(image.id)}>
				<Icon className="wp-block-woogallery-variation-images__image-remove-button" icon="no" style={{
					fontFamily: 'dashicons',
				}} title={__('Remove image', 'gallery-slider-for-woocommerce')} />
			</div>
		</div>
	);
}

export function Edit({ attributes }: { attributes: BlockAttributes }) {
	const blockProps = useWooBlockProps(attributes);
	const { variation_images } = attributes;
	const [variationImages, setVariationImagesValue] = useProductEntityProp(
		variation_images,
		{
			postType: 'product_variation',
			fallbackValue: [],
		}
	);

	const sensors = useSensors(
		useSensor(PointerSensor, {
			// Require a drag distance to prevent accidental drags when clicking
			activationConstraint: {
				distance: 5,
			},
		})
	);

	const handleDragEnd = (event) => {
		const { active, over } = event;
		if (!over) return; // Exit if there's no drop target
		if (active.id !== over.id) {
			const oldIndex = variationImages.findIndex((img) => img.id === active.id);
			const newIndex = variationImages.findIndex((img) => img.id === over.id);
			const newItems = arrayMove(variationImages, oldIndex, newIndex);
			setVariationImagesValue(newItems);
		}
	};
	useEffect(() => {
		console.log('variationImages', variationImages);
	}, [variationImages]);
	// add a useState to store the variationImages
	const [tempImages, setTempImages] = useState(variationImages);

	return (
		<div {...blockProps}>
			<div className="wc-block-gallery-slider-for-woocommerce__variation-images-title">
				<h4>{__('Variation Image Gallery', 'gallery-slider-for-woocommerce')}</h4>
			</div>

			{variationImages && variationImages.length > 0 ? (
				<DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
					<SortableContext items={variationImages.map((img) => img.id)} strategy={verticalListSortingStrategy}>
						<div className="wcgs-gallery-items">
							{variationImages.map((image, index) => (
								index < 2 && (
									<SortableImage key={image.id} image={image} index={index} onRemove={(idToRemove) => {
										const updatedImages = variationImages.filter((img) => img.id != idToRemove);
										setVariationImagesValue(updatedImages);
									}} />)
							))}
						</div>
					</SortableContext>
				</DndContext>
			) : ''}
			<div className='wcgs-gallery-buttons'>
				{variationImages && variationImages.length > 0 ? (
					<>
						<Button
							variant="secondary"
							className="wcgs-remove-all-images button"
							isDestructive
							onClick={() => setVariationImagesValue([])}
						>{__('Remove All', 'gallery-slider-for-woocommerce')}
						</Button>
						<MediaUpload
							allowedTypes="image"
							multiple
							addToGallery={true}
							gallery={true}
							value={variationImages ? variationImages.map((image) => image.id) : []}
							onSelect={(medias) => {
								const newImages = medias.map((media) => ({
									id: media.id,
									url: media.url,
									meta: media,
								}));
								const combined = uniqBy([...(variationImages || []), ...newImages], 'id');
								setTempImages(combined);
								const limited = combined.slice(0, 2);
								setVariationImagesValue(limited);
							}}
							render={({ open }) => (
								<Button onClick={open} className="wcgs-upload-more-image button" variant="secondary" size="compact">
									{__('Add More', 'gallery-slider-for-woocommerce')}
								</Button>
							)}
						/>
						<MediaUpload
							allowedTypes="image"
							multiple
							gallery={true}
							value={variationImages ? variationImages.map((image) => image.id) : []}
							onSelect={(medias) => {
								const newImages = medias.map((media) => ({
									id: media.id,
									url: media.url,
									meta: media,
								}));
								const combined = uniqBy([...(variationImages || []), ...newImages], 'id');
								setTempImages(combined);
								const limited = combined.slice(0, 2);
								setVariationImagesValue(limited);
							}}
							render={({ open }) => (
								<Button onClick={open} variant="secondary"
									className="wcgs-edit button"
									size="compact">
									{__('Edit Gallery', 'gallery-slider-for-woocommerce')}
								</Button>
							)}
						/>

					</>
				) : (
					<MediaUpload
						allowedTypes="image"
						multiple
						gallery={true}
						value={variationImages ? variationImages.map((image) => image.id) : []}
						onSelect={(medias) => {
							const newImages = medias.map((media) => ({
								id: media.id,
								url: media.url,
								meta: media,
							}));
							const combined = uniqBy([...(variationImages || []), ...newImages], 'id');
							setTempImages(combined);
							const limited = combined.slice(0, 2);
							setVariationImagesValue(limited);;
						}}
						render={({ open }) => (
							<Button onClick={open} variant="secondary" className="wcgs-upload-image button" size="compact">
								{__('Add Variation Images', 'gallery-slider-for-woocommerce')}
							</Button>
						)}
					/>
				)}

				{tempImages && tempImages.length > 2 && (
					<span className="wcgs-pro-notice" style={{ color: 'red' }}>
						To add more images &amp; videos,{' '}
						<a href="https://renoproductgallery.com/pricing/?ref=143" target="_blank" style={{ fontStyle: 'italic' }} >
							Upgrade to Pro!
						</a>
					</span>
				)}
			</div>
		</div>
	);
}