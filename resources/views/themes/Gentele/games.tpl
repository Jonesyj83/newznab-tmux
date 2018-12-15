<div class="header"
	>
	<div class="breadcrumb-wrapper">
		<div class="breadcrumb-wrapper">
			<ol class="breadcrumb">
				<li><a href="{$smarty.const.WWW_TOP}{$site->home_link}">Home</a></li>
				/  {if !empty({$catname->parent->title})}<a href="{$smarty.const.WWW_TOP}/browse/{$catname->parent->title}">{$catname->parent->title}</a>{else}<a href="{$smarty.const.WWW_TOP}/browse/{$catname->title}">{$catname->title}</a>{/if}
				/ {if !empty({$catname->parent->title})}<a href="{$smarty.const.WWW_TOP}/browse/{$catname->title}">{$catname->title}</a>{else}All{/if}
			</ol>
		</div>
	</div>
</div>
<div class="well well-sm">
	{include file='search-filter.tpl'}
</div>
{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
	<div class="box-body">
		<div class="row">
			<div class="col-lg-12 col-sm-12 col-12">
				<div class="card card-default">
					<div class="card-body pagination2">
						<div class="row">
							<div class="col-md-4">
								<div class="nzb_multi_operations">
									View: <strong>Covers</strong> | <a
											href="{$smarty.const.WWW_TOP}/browse/Games/{$categorytitle}">List</a><br/>
									With Selected:
									<div class="btn-group">
										<input type="button"
											   class="nzb_multi_operations_download btn btn-sm btn-success"
											   value="Download NZBs"/>
										<input type="button"
											   class="nzb_multi_operations_cart btn btn-sm btn-info"
											   value="Send to my Download Basket"/>
										{if isset($sabintegrated) && $sabintegrated !=""}
											<input type="button"
												   class="nzb_multi_operations_sab btn btn-sm btn-success"
												   value="Send to Queue"/>
										{/if}
										{if isset($isadmin)}
											<input type="button"
												   class="nzb_multi_operations_edit btn btn-sm btn-warning"
												   value="Edit"/>
											<input type="button"
												   class="nzb_multi_operations_delete btn btn-sm btn-danger"
												   value="Delete"/>
										{/if}
									</div>
								</div>
							</div>
							<div class="col-md-8">
								{$results->onEachSide(5)->links()}
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
{{Form::close()}}

<div class="box-body">
	<div class="row">
		<div class="col-lg-12 col-sm-12 col-12">
			<div class="card card-default">
				<div class="card-body">
					<div class="row">
						<div class="col-md-8">
							{if count($results) > 0}
								{foreach $results as $result}
									<div class="card card-default">
										<div class="card-body">
											<div class="row">
												<div class="col-md-2 small-gutter-left">
													<a title="View details"
													   href="{$smarty.const.WWW_TOP}/details/{$result->guid}">
														<img src="{$smarty.const.WWW_TOP}/covers/games/{if $result->cover == 1}{$result->gamesinfo_id}.jpg{else}{$smarty.const.WWW_ASSETS}/images/no-cover.png{/if}"
															 class="img-fluid rounded"
															 width="140" border="0"
															 alt="{$result->title|escape:"htmlall"}"/>{if !empty($result->failed)}
														<i class="fa fa-exclamation-circle" style="color: red"
														   title="This release has failed to download for some users"></i>{/if}
													</a>
													{if $result->classused == "GiantBomb"}<a
														class="badge badge-default"
														target="_blank"
														href="{$site->dereferrer_link}{$result->url}"
														name="giantbomb{$result->gamesinfo_id}"
														title="View GiantBomb page">
															GiantBomb</a>{/if}
													{if $result->classused == "Steam"}<a
														class="badge badge-default"
														target="_blank"
														href="{$site->dereferrer_link}{$result->url|escape:"htmlall"}"
														name="steam{$result->gamesinfo_id}"
														title="View Steam page">
															Steam</a>{/if}
                                                    {if $result->classused == "IGDB"}<a
                                                        class="badge badge-default"
                                                        target="_blank"
                                                        href="{$site->dereferrer_link}{$result->url}"
                                                        name="igdb{$result->gamesinfo_id}"
                                                        title="View IGDB page">
                                                            IGDB</a>{/if}
													{if $result->nfoid > 0}<a
														href="{$smarty.const.WWW_TOP}/nfo/{$result->guid}"
														title="View NFO" class="modal_nfo badge badge-default"
														rel="nfo">
															NFO</a>{/if}
													<a class="badge badge-default"
													   href="{$smarty.const.WWW_TOP}/browse/group?g={$result->group_name}"
													   title="Browse releases in {$result->group_name|replace:"alt.binaries":"a.b"}">Group</a>
													{if !empty($result->failed)}
														<span class="btn btn-light btn-xs"
															  title="This release has failed to download for some users">
														<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
															Grab{if {$result->grabs} != 1}s{/if} / <i
																	class="fa fa-thumbs-o-down"></i> {$result->failed}
															Failed Download{if {$result->failed} > 1}s{/if}</span>
													{/if}
												</div>
												<div class="col-md-10 small-gutter-left">
													<h4><a title="View details"
														   href="{$smarty.const.WWW_TOP}/details/{$result->guid}">{$result->title|escape:"htmlall"}</a>
													</h4>
													<table class="data table table-responsive">
														<tr>
															<td id="guid{$result->guid}">
																<label>
																	<input type="checkbox"
																		   class="square"
																		   value="{$result->guid}"
																		   id="chksingle"/>
																</label>
																<span class="badge badge-default">{$result->size|filesize}</span>
																<span class="badge badge-default">Posted {$result->postdate|timeago}
																	ago</span>
																{if isset($isadmin)}<a
																	class="badge badge-warning"
																	href="{$smarty.const.WWW_TOP}/admin/release-edit?id={$result->guid}"
																	title="Edit release">
																		Edit</a>{/if}
																<br/>
																{if isset($result->genre) && $result->genre != ""}
																	<b>Genre:</b>
																	{$result->genre}
																	<br/>
																{/if}
																{if isset($result->esrb) && $result->esrb != ""}
																	<b>Rating:</b>
																	{$result->esrb}
																	<br/>
																{/if}
																{if isset($result->publisher) && $result->publisher != ""}
																	<b>Publisher:</b>
																	{$result->publisher}
																	<br/>
																{/if}
																{if isset($result->releasedate) && $result->releasedate != ""}
																	<b>Released:</b>
																	{$result->releasedate|date_format}
																	<br/>
																{/if}
																{if isset($result->review) && $result->review != ""}
																	<b>Review:</b>
																	{$result->review|stripslashes|escape:'htmlall'}
																	<br/>
																{/if}
																<div>
																	<a role="button"
																	   class="btn btn-light btn-xs"
																	   href="{$smarty.const.WWW_TOP}/getnzb?id={$result->guid}"><i
																				class="fa fa-cloud-download"></i><span
																				class="badge"> {$result->grabs}
																			Grab{if $result->grabs != 1}s{/if}</span></a>
																	<a role="button"
																	   class="btn btn-light btn-xs"
																	   href="{$smarty.const.WWW_TOP}/details/{$result->guid}/#comments"><i
																				class="fa fa-comment-o"></i><span
																				class="badge"> {$result->comments}
																			Comment{if $result->comments != 1}s{/if}</span></a>
																	<span
																			id="guid{$result->guid}"
																			class="btn btn-hover btn-light btn-xs icon_cart text-muted"
																			title="Send to my Download Basket">
																	<i class="fa fa-shopping-basket"></i>
															</span>
																	{if isset($sabintegrated) && $sabintegrated !=""}
																		<span
																				id="guid{$result->guid}"
																				class="btn btn-hover btn-light btn-xs icon_sab text-muted"
																				title="Send to my Queue">
																		<i class="fa fa-share"></i>
																</span>
																	{/if}
																	{if !empty($result->failed)}
																		<span class="btn btn-light btn-xs"
																			  title="This release has failed to download for some users">
																	<i class="fa fa-thumbs-o-up"></i> {$result->grabs}
																			Grab{if {$result->grabs} != 1}s{/if}
																			/ <i class="fa fa-thumbs-o-down"></i> {$result->failed}
																			Failed Download{if {$result->failed} > 1}s{/if}</span>
																	{/if}
																</div>
															</td>
														</tr>
													</table>
												</div>
											</div>
										</div>
									</div>
								{/foreach}
							{else}
								No games with covers available!
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
{{Form::open(['id' => 'nzb_multi_operations_form', 'method' => 'get'])}}
	<div class="box-body">
		<div class="row">
			<div class="col-lg-12 col-sm-12 col-12">
				<div class="card card-default">
					<div class="card-body pagination2">
						<div class="row">
							<div class="col-md-4">
								<div class="nzb_multi_operations">
									View: <strong>Covers</strong> | <a
											href="{$smarty.const.WWW_TOP}/browse/Games/{$categorytitle}">List</a><br/>
									Check all: <input type="checkbox" class="square-all"/>
									<br/>
									With Selected:
									<div class="btn-group">
										<input type="button"
											   class="nzb_multi_operations_download btn btn-sm btn-success"
											   value="Download NZBs"/>
										<input type="button"
											   class="nzb_multi_operations_cart btn btn-sm btn-info"
											   value="Send to my Download Basket"/>
										{if isset($sabintegrated) && $sabintegrated !=""}
											<input type="button"
												   class="nzb_multi_operations_sab btn btn-sm btn-success"
												   value="Send to Queue"/>
										{/if}
										{if isset($isadmin)}
											<input type="button"
												   class="nzb_multi_operations_edit btn btn-sm btn-warning"
												   value="Edit"/>
											<input type="button"
												   class="nzb_multi_operations_delete btn btn-sm btn-danger"
												   value="Delete"/>
										{/if}
									</div>
								</div>
							</div>
							{if count($results) > 0}
								<div class="col-md-8">
									{$results->onEachSide(5)->links()}
								</div>
							{/if}
						</div>
					</div>
				</div>
			</div>
		</div>
{{Form::close()}}
