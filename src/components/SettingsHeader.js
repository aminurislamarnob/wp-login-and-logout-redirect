const SettingsHeader = ( { icon: Icon, logo, title, subTitle, actions } ) => {
	return (
		<div className="wplalr-header-wrapper">
			<div className="wplalr-settings-header">
				<div className="wplalr-settings-header-inner">
					<div className="wplalr-header-text-column">
						<div className="wplalr-header-title-container">
							{ logo ? (
								<img
									className="wplalr-settings-header-logo"
									src={ logo }
									alt={ title }
								/>
							) : (
								<>
									{ Icon && (
										<div className="wplalr-settings-header-icon">
											<Icon />
										</div>
									) }
									<h2>{ title }</h2>
								</>
							) }
						</div>
						{ subTitle && (
							<p className="wplalr-header-subtitle">
								{ subTitle }
							</p>
						) }
					</div>
					{ actions && (
						<div className="wplalr-header-actions">{ actions }</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default SettingsHeader;
