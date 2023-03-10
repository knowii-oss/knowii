describe('client-ui: EditableText component', () => {
  beforeEach(() => cy.visit('/iframe.html?id=editabletext--primary'));

  it('should render the component', () => {
    cy.get('h1').should('contain', 'Welcome to EditableText!');
  });
});
