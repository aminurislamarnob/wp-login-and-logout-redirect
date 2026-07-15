/**
 * Generate a stable client-side id for a rule or condition.
 *
 * Used so drag-and-drop and React keys work before the server assigns a UUID.
 *
 * @return {string} A unique identifier.
 */
export const makeId = () => {
	if (
		typeof window !== 'undefined' &&
		window.crypto &&
		typeof window.crypto.randomUUID === 'function'
	) {
		return window.crypto.randomUUID();
	}

	return `wplalr-${ Date.now() }-${ Math.random()
		.toString( 36 )
		.slice( 2, 10 ) }`;
};

/**
 * A fresh, empty condition.
 *
 * @return {Object} Condition object.
 */
export const emptyCondition = () => ( {
	id: makeId(),
	type: 'role',
	values: [],
} );

/**
 * A fresh, empty rule.
 *
 * @return {Object} Rule object.
 */
export const emptyRule = () => ( {
	id: makeId(),
	enabled: true,
	label: '',
	conditions: [],
	login_url: '',
	logout_url: '',
} );
