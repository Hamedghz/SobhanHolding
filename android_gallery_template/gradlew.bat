@ECHO OFF
SETLOCAL

set DIR=%~dp0

IF EXIST "%DIR%\gradle\wrapper\gradle-wrapper.jar" (
  java -Xmx1024m -Dfile.encoding=UTF-8 -classpath "%DIR%\gradle\wrapper\gradle-wrapper.jar" org.gradle.wrapper.GradleWrapperMain %*
) ELSE (
  ECHO Gradle wrapper JAR not found. Please install Gradle and run "gradle wrapper" first.
  EXIT /B 1
)
