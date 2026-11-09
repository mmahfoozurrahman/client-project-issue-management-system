/**
 * Build the detail URL for a project.
 *
 * The backend appends a `url` attribute shaped like `/projects/{id}/{slug}`.
 * Fall back to the plain id-based URL when the slug is unavailable
 * (for example, hand-built objects that were not serialized from the model).
 */
export function projectUrl(project) {
    if (project?.url) {
        return project.url;
    }

    return project?.id ? `/projects/${project.id}` : '/projects';
}
