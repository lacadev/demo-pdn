const ROOT_SELECTOR = '#laca-project-archive';
const GRID_ID = 'project-grid';
const PAGINATION_ID = 'project-pagination';
const FILTER_SELECTOR = '.laca-gallery-filter';

async function fetchArchive({ config, catSlug, paged }) {
	const body = new URLSearchParams({
		action: config.action,
		nonce: config.nonce,
		cat_slug: catSlug,
		paged,
		posts_per_page: config.posts_per_page,
	});

	const response = await fetch(config.ajaxurl, { method: 'POST', body });
	return response.json();
}

function updatePage({ gridEl, paginationEl, filterEl, html, pagination, activeLabel, catSlug, paged, archiveUrl, queryParam }) {
	gridEl.innerHTML = html;
	paginationEl.innerHTML = pagination;

	const titleEl = document.querySelector('.laca-gallery-toolbar__title');
	if (titleEl) titleEl.textContent = activeLabel;

	const labelEl = filterEl.querySelector('.laca-gallery-filter__label');
	if (labelEl) labelEl.textContent = activeLabel;

	filterEl.querySelectorAll('[data-cat-slug]').forEach((item) => {
		item.classList.toggle('is-active', item.dataset.catSlug === catSlug);
	});

	const url = new URL(archiveUrl);
	if (catSlug) url.searchParams.set(queryParam, catSlug);
	else url.searchParams.delete(queryParam);
	if (paged > 1) url.searchParams.set('paged', paged);
	else url.searchParams.delete('paged');
	history.pushState({ catSlug, paged }, '', url.toString());
}

function buildArchiveUrl({ archiveUrl, queryParam, catSlug, paged }) {
	const url = new URL(archiveUrl);
	if (catSlug) url.searchParams.set(queryParam, catSlug);
	else url.searchParams.delete(queryParam);
	if (paged > 1) url.searchParams.set('paged', paged);
	else url.searchParams.delete('paged');
	return url.toString();
}

function init() {
	const root = document.querySelector(ROOT_SELECTOR);
	if (!root) return;

	const gridEl = document.getElementById(GRID_ID);
	if (!gridEl) return;

	const config = JSON.parse(root.dataset.archiveConfig || '{}');
	if (!config.ajaxurl || !config.action) return;

	const paginationEl = document.getElementById(PAGINATION_ID);
	const filterEl = root.querySelector(FILTER_SELECTOR);
	const queryParam = config.query_param || 'project_cat';
	let currentCat = config.cat_slug || '';

	if (filterEl && !filterEl.dataset.bound) {
		filterEl.dataset.bound = '1';
		const trigger = filterEl.querySelector('.laca-gallery-filter__trigger');
		const list = filterEl.querySelector('.laca-gallery-filter__list');

		if (trigger && list) {
			trigger.addEventListener('click', () => {
				const isOpen = filterEl.classList.toggle('is-open');
				trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});

			document.addEventListener('click', (e) => {
				if (!filterEl.contains(e.target)) {
					filterEl.classList.remove('is-open');
					trigger.setAttribute('aria-expanded', 'false');
				}
			});

			list.addEventListener('click', async (e) => {
				const item = e.target.closest('[data-cat-slug]');
				if (!item) return;
				e.preventDefault();

				const catSlug = item.dataset.catSlug;
				if (catSlug === currentCat) {
					filterEl.classList.remove('is-open');
					return;
				}

				filterEl.classList.remove('is-open');
				gridEl.classList.add('is-loading');
				try {
					const result = await fetchArchive({ config, catSlug, paged: 1 });
					if (result.success) {
						currentCat = catSlug;
						updatePage({
							gridEl,
							paginationEl,
							filterEl,
							html: result.data.html,
							pagination: result.data.pagination,
							activeLabel: result.data.active_label,
							catSlug,
							paged: 1,
							archiveUrl: config.archive_url,
							queryParam,
						});
					} else {
						window.location.href = buildArchiveUrl({
							archiveUrl: config.archive_url,
							queryParam,
							catSlug,
							paged: 1,
						});
					}
				} catch (error) {
					window.location.href = buildArchiveUrl({
						archiveUrl: config.archive_url,
						queryParam,
						catSlug,
						paged: 1,
					});
				} finally {
					gridEl.classList.remove('is-loading');
				}
			});
		}
	}

	if (paginationEl && !paginationEl.dataset.bound) {
		paginationEl.dataset.bound = '1';
		paginationEl.addEventListener('click', async (e) => {
			const link = e.target.closest('a');
			if (!link) return;
			e.preventDefault();

			const paged = parseInt(new URL(link.href).searchParams.get('paged') || '1', 10);
			gridEl.classList.add('is-loading');
			try {
				const result = await fetchArchive({ config, catSlug: currentCat, paged });
				if (result.success) {
					updatePage({
						gridEl,
						paginationEl,
						filterEl,
						html: result.data.html,
						pagination: result.data.pagination,
						activeLabel: result.data.active_label,
						catSlug: currentCat,
						paged,
						archiveUrl: config.archive_url,
						queryParam,
					});
					window.scrollTo({ top: root.offsetTop - 80, behavior: 'smooth' });
				} else {
					window.location.href = buildArchiveUrl({
						archiveUrl: config.archive_url,
						queryParam,
						catSlug: currentCat,
						paged,
					});
				}
			} catch (error) {
				window.location.href = buildArchiveUrl({
					archiveUrl: config.archive_url,
					queryParam,
					catSlug: currentCat,
					paged,
				});
			} finally {
				gridEl.classList.remove('is-loading');
			}
		});
	}
}

let hookedBarba = false;

function bootstrap() {
	init();
	setTimeout(() => {
		if (window.barba && !hookedBarba) {
			hookedBarba = true;
			window.barba.hooks.after(() => init());
		}
	}, 0);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', bootstrap);
} else {
	bootstrap();
}

