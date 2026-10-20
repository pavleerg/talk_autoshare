/**
 * Switches Talk's recording view (/call/{token}/recording) to grid view and keeps it there.
 *
 * Talk's recording bundle starts with callView.isGrid = false and, unlike the normal UI, never applies
 * the grid default for group calls; later joins can reset it too, so the store is watched for the
 * whole recording. Screen shares (presentationStarted) are left to Talk, which restores the previous
 * view afterwards. Uses Talk 24 internals (Vue 3 app exposed as OCA.Talk.instance, Pinia store
 * "callView"); if they change, the recording silently stays in speaker view.
 */
(function () {
	const match = window.location.pathname.match(/\/call\/([^/]+)\/recording/)
	if (!match) {
		return
	}
	const token = match[1]
	const deadline = Date.now() + 120000

	const applyGrid = (store) => {
		store.setCallViewMode({ token, isGrid: true, isStripeOpen: true })
	}

	const timer = setInterval(() => {
		try {
			const pinia = window.OCA?.Talk?.instance?.$pinia
				?? document.querySelector('#content')?.__vue_app__?.config.globalProperties.$pinia
			const store = pinia?._s?.get('callView')
			if (store) {
				clearInterval(timer)
				applyGrid(store)
				console.info('[talk_autoshare] recording switched to grid view')
				store.$subscribe(() => {
					if (!store.isGrid && !store.presentationStarted) {
						applyGrid(store)
						console.info('[talk_autoshare] call view was reset, switched back to grid view')
					}
				})
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
