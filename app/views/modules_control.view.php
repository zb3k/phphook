<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>CMS Hook</title>
	<link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
	<div class="navbar navbar-default">
		<div class="container">
			<div class="navbar-header">
				<span class="navbar-brand">CMS Hook <span class="label label-info"><?=$version ?></span></span>
			</div>
			<p class="navbar-text">
				for <?=$cms_name ?> <span class="label label-info"><?=$cms_version ?></span>
			</p>

			<div class="navbar-right navbar-form">
				<? if (count(scandir($this->path('storage'))) > 2): ?>
					<a href="?route=restore" class="btn btn-danger">Restore</a>
				<? endif ?>
			</div>

		</div>

	</div>


	<div class="container">
		<? if (!$all_modules): ?>
			<div class="alert alert-warning">No modules</div>
		<? endif ?>

		<? foreach (array('Installed mods'=>$installed_mods, 'Not installed mods'=>$not_installed_mods) as $title => $modules): ?>
			<? if ($modules): ?>
				<table class="table table-bordered">
					<col />
					<col width="25%" />
					<col width="10%" />
					<thead>
						<tr class='active'>
							<th><?=$title ?></th>
							<th>Require</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
					<? foreach ($modules as $key => $mod): ?>
						<tr>
							<td>
								<div class="pull-right">
									<? if ($mod->site): ?><a class='btn btn-default' href="<?=$mod->site ?>">Site</a><? endif ?>
								</div>
								<b class='text-<?=$mod->installed ? 'success' : 'danger' ?> '><?=$mod->name ?></b>

								<span class="label label-default"><?=$mod->version ?></span>
								<? if ($mod->author): ?>
										<? if ($mod->email): ?>
											<a class="label label-default" href="mailto:<?=$mod->email ?>"><?=$mod->author ?></a>
										<? else: ?>
											<span class="label label-default"><?=$mod->author ?></span>
										<? endif ?>
								<? endif ?>
								<br>
								<small class="text-muted"><?=$mod->description ?></small>
							</td>
							<td>
								<? if ($mod->require): ?>
									<? foreach ($mod->require as $mod_name => $mod_ver): ?>
										<span class='label label-default'><?=$mod_name ?> <?=$mod_ver ?></span>
									<? endforeach ?>
								<? endif ?>
							</td>
							<!-- <td>
								Simpla: <span class='label label-default'><?=$mod->simpla_version ?></span>
								CmsHook: <span class='label label-default'><?=$mod->cmshook_version ?></span>
							</td> -->
							<td class="text-center">
								<? if ($mod->installed): ?>
									<a class="btn btn-danger" href='?route=uninstall/<?=$mod->name ?>'>Uninstall</a>
								<? else: ?>
									<a class="btn btn-info" href='?route=install/<?=$mod->name ?>'>Install</a>
								<? endif ?>
								<!-- <div class="btn-group">
									<a class="btn btn-danger active">ОТКЛ</a>
									<a class="btn btn-default">ВКЛ</a>
								</div> -->
							</td>
						</tr>
					<? endforeach ?>
					</tbody>
				</table>
			<? endif ?>
		<? endforeach ?>

	</div>
</body>
</html>