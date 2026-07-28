Set WshShell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

projectDir = ""
pathFile = WshShell.ExpandEnvironmentStrings("%USERPROFILE%") & "\.ai-helper\project_dir.txt"

If fso.FileExists(pathFile) Then
    Set tf = fso.OpenTextFile(pathFile, 1, False, -1)
    projectDir = Trim(tf.ReadAll())
    tf.Close
End If

If projectDir = "" Or Not fso.FileExists(projectDir & "\launcher.py") Then
    projectDir = fso.GetParentFolderName(WScript.ScriptFullName)
End If

If Not fso.FileExists(projectDir & "\START.bat") Then
    MsgBox "Не найден START.bat в папке:" & vbCrLf & projectDir & vbCrLf & vbCrLf & "Запусти ""Установить на рабочий стол.bat"" из папки project.", vbCritical, "AI Helper"
    WScript.Quit 1
End If

If Not fso.FolderExists(WshShell.ExpandEnvironmentStrings("%USERPROFILE%") & "\.ai-helper") Then
    fso.CreateFolder WshShell.ExpandEnvironmentStrings("%USERPROFILE%") & "\.ai-helper"
End If

Set outFile = fso.CreateTextFile(pathFile, True, False)
outFile.Write projectDir
outFile.Close

WshShell.CurrentDirectory = projectDir
WshShell.Run "cmd /c """ & projectDir & "\START.bat""", 1, True
