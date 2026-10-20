/**
 * Switches Talk's recording view (/call/{token}/recording) to grid view.
 *
 * Talk's recording bundle starts with callView.isGrid = false and, unlike the normal UI, never applies
 * the grid default for group calls. Uses Talk 24 internals (Vue 3 app exposed as OCA.Talk.instance,
 * Pinia store "callView"); if they change, the recording silently stays in speaker view.
 */
(function () {
	const match = window.location.pathname.match(/\/call\/([^/]+)\/recording/)
	if (!match) {
		return
	}
	const token = match[1]
	const deadline = Date.now() + 30000

	const timer = setInterval(() => {
		try {
			const pinia = window.OCA?.Talk?.instance?.$pinia
				?? document.querySelector('#content')?.__vue_app__?.config.globalProperties.$pinia
			const store = pinia?._s?.get('callView')
			if (store) {
				clearInterval(timer)
				store.setCallViewMode({ token, isGrid: true, isStripeOpen: true })
				console.info('[talk_autoshare] recording switched to grid view')
				return
			}
		} catch (e) {
			clearInterval(timer)
			console.warn('[talk_autoshare] could not switch recording to grid view', e)
			return
		}
		if (Date.now() > deadline) {
			clearInterval(timer)
			console.warn('[talk_autoshare] callView store not found, recording stays in speaker view')
		}
	}, 250)
})()
